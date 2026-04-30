<?php

namespace App\Http\Requests\StudentPayment;

use App\Services\StudentPayment\StudentPaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'index' => $this->indexRules(),
            'store', 'update' => $this->paymentRules(),
            default => [],
        };
    }

    private function indexRules(): array
    {
        return [
            'student_id' => 'nullable|integer|exists:students,id',
            'status' => 'nullable|string|in:pending,partial,paid,overdue,cancelled',
            'payment_plan' => ['nullable', 'string', Rule::in(StudentPaymentService::paymentPlanKeys())],
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'size' => 'nullable|integer|min:1|max:100',
        ];
    }

    private function paymentRules(): array
    {
        $ignoreId = $this->route('id');

        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where(fn ($query) => $query->whereIn('student_type', ['PAY', 'PASS'])),
            ],
            'invoice_no' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('student_payments', 'invoice_no')->ignore($ignoreId),
            ],
            'academic_year' => 'nullable|string|max:20',
            'term' => 'nullable|string|max:50',
            'payment_plan' => ['required', 'string', Rule::in(StudentPaymentService::paymentPlanKeys())],
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
        ];
    }
}
