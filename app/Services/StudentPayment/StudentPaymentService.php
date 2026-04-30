<?php

namespace App\Services\StudentPayment;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\StudentPayment\StudentPaymentResource;
use App\Models\StudentPayment;
use App\Services\Concerns\ServiceTraceable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StudentPaymentService
{
    use ServiceTraceable;

    private const BASE_TUITION = 600.00;

    private const PAYMENT_PLANS = [
        'PAY_FULL' => ['label' => 'Pay Full', 'payable' => 600.00],
        'SCHOLARSHIP_FULL' => ['label' => 'Scholarship Full', 'payable' => 50.00],
        'SCHOLARSHIP_70' => ['label' => 'Scholarship 70%', 'payable' => 100.00],
        'SCHOLARSHIP_50' => ['label' => 'Scholarship 50%', 'payable' => 400.00],
        'SCHOLARSHIP_30' => ['label' => 'Scholarship 30%', 'payable' => 300.00],
    ];

    public static function paymentPlanKeys(): array
    {
        return array_keys(self::PAYMENT_PLANS);
    }

    public static function paymentPlanLabel(?string $key): ?string
    {
        return self::PAYMENT_PLANS[$key]['label'] ?? null;
    }

    public function plans(): array
    {
        return $this->trace(__FUNCTION__, function (): array {
            return [
                'base_tuition' => self::BASE_TUITION,
                'plans' => collect(self::PAYMENT_PLANS)->map(fn (array $plan, string $key): array => [
                    'value' => $key,
                    'label' => $plan['label'],
                    'payable' => $plan['payable'],
                    'discount' => max(0, self::BASE_TUITION - $plan['payable']),
                ])->values(),
            ];
        });
    }

    public function list(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $query = $this->filteredQuery($filters);
            $summary = $this->summaryFor(clone $query);
            $perPage = max(1, min((int) ($filters['per_page'] ?? $filters['size'] ?? 10), 100));
            $paginator = $query->orderByDesc('created_at')->paginate($perPage);
            $page = PaginatedResult::fromPaginator($paginator, StudentPaymentResource::class);

            return [
                'items' => $page->items,
                'pagination' => [
                    'total' => $page->total,
                    'per_page' => $page->perPage,
                    'current_page' => $page->currentPage,
                    'total_pages' => $page->totalPages,
                ],
                'summary' => $summary,
            ];
        });
    }

    public function create(array $data): StudentPayment
    {
        return $this->trace(__FUNCTION__, function () use ($data): StudentPayment {
            $validated = $this->normalizePaymentData($data);

            return DB::transaction(function () use ($validated): StudentPayment {
                $payment = StudentPayment::create($validated);
                $payment->forceFill([
                    'invoice_no' => $validated['invoice_no'] ?? $this->nextInvoiceNo($payment->id),
                ])->save();

                return $this->loadPayment($payment);
            });
        });
    }

    public function get(int $id): StudentPayment
    {
        return $this->trace(__FUNCTION__, fn (): StudentPayment => $this->findOrFail($id));
    }

    public function update(int $id, array $data): StudentPayment
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): StudentPayment {
            $payment = $this->findOrFail($id, false);
            $payment->update($this->normalizePaymentData($data));

            return $this->loadPayment($payment);
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id): void {
            $this->findOrFail($id, false)->delete();
        });
    }

    private function filteredQuery(array $filters): Builder
    {
        return StudentPayment::query()
            ->with(['student.academicInfo.major', 'student.academicInfo.shift'])
            ->when(! empty($filters['student_id']), fn (Builder $query) => $query->where('student_id', (int) $filters['student_id']))
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['payment_plan']), fn (Builder $query) => $query->where('payment_plan', $filters['payment_plan']))
            ->when(! empty($filters['from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->when(! empty($filters['search']), fn (Builder $query) => $this->applySearch($query, $filters['search']));
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        $query->where(function (Builder $query) use ($search) {
            $query->where('invoice_no', 'like', "%{$search}%")
                ->orWhere('reference_no', 'like', "%{$search}%")
                ->orWhereHas('student', function (Builder $student) use ($search) {
                    $student->where('full_name_en', 'like', "%{$search}%")
                        ->orWhere('full_name_kh', 'like', "%{$search}%")
                        ->orWhere('id_card_number', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%");
                });
        });
    }

    private function normalizePaymentData(array $data): array
    {
        $payableAmount = self::PAYMENT_PLANS[$data['payment_plan']]['payable'];
        $data['amount_due'] = self::BASE_TUITION;
        $data['discount'] = max(0, self::BASE_TUITION - $payableAmount);
        $data['amount_paid'] = (float) ($data['amount_paid'] ?? 0);
        $data['payment_type'] = $data['payment_type'] ?: 'Tuition Fee';
        $data['status'] = $this->resolveStatus($data);

        return $data;
    }

    private function resolveStatus(array $data): string
    {
        if (($data['status'] ?? null) === 'cancelled') {
            return 'cancelled';
        }

        $due = max(0, (float) ($data['amount_due'] ?? 0) - (float) ($data['discount'] ?? 0));
        $paid = (float) ($data['amount_paid'] ?? 0);

        if ($due <= 0 || $paid >= $due) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partial';
        }

        if (! empty($data['due_date']) && Carbon::parse($data['due_date'])->lt(Carbon::today())) {
            return 'overdue';
        }

        return 'pending';
    }

    private function nextInvoiceNo(int $id): string
    {
        return 'PAY-' . now()->format('ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    private function summaryFor(Builder $query): array
    {
        $rows = $query->selectRaw(
            'COUNT(*) as records,
            COALESCE(SUM(amount_due), 0) as total_due,
            COALESCE(SUM(discount), 0) as total_discount,
            COALESCE(SUM(amount_paid), 0) as total_paid,
            SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) as overdue_count'
        )->first();

        $netDue = (float) $rows->total_due - (float) $rows->total_discount;

        return [
            'records' => (int) $rows->records,
            'total_due' => round((float) $rows->total_due, 2),
            'total_discount' => round((float) $rows->total_discount, 2),
            'net_due' => round(max(0, $netDue), 2),
            'total_paid' => round((float) $rows->total_paid, 2),
            'balance' => round(max(0, $netDue - (float) $rows->total_paid), 2),
            'overdue_count' => (int) $rows->overdue_count,
        ];
    }

    private function findOrFail(int $id, bool $withRelations = true): StudentPayment
    {
        $query = StudentPayment::query();

        if ($withRelations) {
            $query->with(['student.academicInfo.major', 'student.academicInfo.shift']);
        }

        $payment = $query->find($id);

        if (! $payment) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Student payment not found.');
        }

        return $payment;
    }

    private function loadPayment(StudentPayment $payment): StudentPayment
    {
        return $payment->load(['student.academicInfo.major', 'student.academicInfo.shift']);
    }
}
