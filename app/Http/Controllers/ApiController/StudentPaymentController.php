<?php

namespace App\Http\Controllers\ApiController;

use App\DTOs\PaginatedResult;
use App\Http\Controllers\Controller;
use App\Models\StudentPayment;
use App\Models\Students;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentPaymentController extends Controller
{
    use ApiResponseTrait;

    private const BASE_TUITION = 600.00;

    private const PAYMENT_PLANS = [
        'PAY_FULL' => ['label' => 'Pay Full', 'payable' => 600.00],
        'SCHOLARSHIP_FULL' => ['label' => 'Scholarship Full', 'payable' => 50.00],
        'SCHOLARSHIP_70' => ['label' => 'Scholarship 70%', 'payable' => 100.00],
        'SCHOLARSHIP_50' => ['label' => 'Scholarship 50%', 'payable' => 400.00],
        'SCHOLARSHIP_30' => ['label' => 'Scholarship 30%', 'payable' => 300.00],
    ];

    public function plans()
    {
        return $this->success([
            'base_tuition' => self::BASE_TUITION,
            'plans' => collect(self::PAYMENT_PLANS)->map(fn ($plan, $key) => [
                'value' => $key,
                'label' => $plan['label'],
                'payable' => $plan['payable'],
                'discount' => max(0, self::BASE_TUITION - $plan['payable']),
            ])->values(),
        ], 'Student payment plans retrieved successfully.');
    }

    public function index(Request $request)
    {
        $query = StudentPayment::query()
            ->with(['student.academicInfo.major', 'student.academicInfo.shift'])
            ->when($request->filled('student_id'), fn (Builder $q) => $q->where('student_id', $request->integer('student_id')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->status))
            ->when($request->filled('payment_plan'), fn (Builder $q) => $q->where('payment_plan', $request->payment_plan))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $search = trim((string) $request->search);
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('invoice_no', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('student', function (Builder $student) use ($search) {
                            $student->where('full_name_en', 'like', "%{$search}%")
                                ->orWhere('full_name_kh', 'like', "%{$search}%")
                                ->orWhere('id_card_number', 'like', "%{$search}%")
                                ->orWhere('id', 'like', "%{$search}%");
                        });
                });
            });

        $summary = $this->summaryFor(clone $query);
        $perPage = (int) $request->input('per_page', $request->input('size', 10));
        $paginated = $query->orderByDesc('created_at')->paginate(max(1, min($perPage, 100)));
        $page = PaginatedResult::fromPaginator($paginated);

        return $this->success([
            'items' => collect($page->items)->map(fn (StudentPayment $payment) => $this->paymentPayload($payment))->values(),
            'pagination' => [
                'total' => $page->total,
                'per_page' => $page->perPage,
                'current_page' => $page->currentPage,
                'total_pages' => $page->totalPages,
            ],
            'summary' => $summary,
        ], 'Student payments retrieved successfully.');
    }

    public function store(Request $request)
    {
        $validated = $this->validatedPayment($request);
        $validated['status'] = $this->resolveStatus($validated);

        $payment = DB::transaction(function () use ($validated) {
            $payment = StudentPayment::create($validated);
            $payment->forceFill([
                'invoice_no' => $validated['invoice_no'] ?? $this->nextInvoiceNo($payment->id),
            ])->save();

            return $payment->load(['student.academicInfo.major', 'student.academicInfo.shift']);
        });

        return $this->success($this->paymentPayload($payment), 'Student payment created successfully.');
    }

    public function show(int $id)
    {
        $payment = StudentPayment::with(['student.academicInfo.major', 'student.academicInfo.shift'])->findOrFail($id);

        return $this->success($this->paymentPayload($payment), 'Student payment retrieved successfully.');
    }

    public function update(Request $request, int $id)
    {
        $payment = StudentPayment::findOrFail($id);
        $validated = $this->validatedPayment($request, $payment->id);
        $validated['status'] = $this->resolveStatus($validated);

        $payment->update($validated);
        $payment->load(['student.academicInfo.major', 'student.academicInfo.shift']);

        return $this->success($this->paymentPayload($payment), 'Student payment updated successfully.');
    }

    public function destroy(int $id)
    {
        StudentPayment::findOrFail($id)->delete();

        return $this->success(null, 'Student payment deleted successfully.');
    }

    private function validatedPayment(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q->whereIn('student_type', ['PAY', 'PASS'])),
            ],
            'invoice_no' => ['nullable', 'string', 'max:50', Rule::unique('student_payments', 'invoice_no')->ignore($ignoreId)],
            'academic_year' => 'nullable|string|max:20',
            'term' => 'nullable|string|max:50',
            'payment_plan' => ['required', 'string', Rule::in(array_keys(self::PAYMENT_PLANS))],
            'payment_type' => 'required|string|max:80',
            'amount_due' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'status' => 'nullable|string|in:pending,partial,paid,overdue,cancelled',
            'payment_method' => 'nullable|string|max:50',
            'reference_no' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:1000',
        ]);

        $payableAmount = self::PAYMENT_PLANS[$data['payment_plan']]['payable'];
        $data['amount_due'] = self::BASE_TUITION;
        $data['discount'] = max(0, self::BASE_TUITION - $payableAmount);
        $data['amount_paid'] = (float) ($data['amount_paid'] ?? 0);
        $data['payment_type'] = $data['payment_type'] ?: 'Tuition Fee';

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

        if (!empty($data['due_date']) && Carbon::parse($data['due_date'])->lt(Carbon::today())) {
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

    private function paymentPayload(StudentPayment $payment): array
    {
        $student = $payment->student;
        $academic = $student?->academicInfo;

        return [
            'id' => $payment->id,
            'student_id' => $payment->student_id,
            'student_name' => $student?->full_name_en ?: $student?->full_name_kh,
            'student_name_kh' => $student?->full_name_kh,
            'student_code' => $student?->id_card_number,
            'student_type' => $student?->student_type,
            'student_tuition_plan' => $student?->tuition_plan,
            'student_tuition_plan_label' => self::PAYMENT_PLANS[$student?->tuition_plan]['label'] ?? null,
            'major_name' => $academic?->major?->name,
            'year' => $academic?->stage,
            'shift_name' => $academic?->shift?->name,
            'invoice_no' => $payment->invoice_no,
            'academic_year' => $payment->academic_year,
            'term' => $payment->term,
            'payment_plan' => $payment->payment_plan,
            'payment_plan_label' => self::PAYMENT_PLANS[$payment->payment_plan]['label'] ?? $payment->payment_plan,
            'payable_amount' => max(0, (float) $payment->amount_due - (float) $payment->discount),
            'payment_type' => $payment->payment_type,
            'amount_due' => (float) $payment->amount_due,
            'discount' => (float) $payment->discount,
            'amount_paid' => (float) $payment->amount_paid,
            'balance' => $payment->balance,
            'due_date' => $payment->due_date?->format('Y-m-d'),
            'paid_at' => $payment->paid_at?->format('Y-m-d'),
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'reference_no' => $payment->reference_no,
            'note' => $payment->note,
            'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $payment->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
