<?php

namespace App\Http\Resources\StudentPayment;

use App\Services\StudentPayment\StudentPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $student = $this->resource->student;
        $academic = $student?->academicInfo;

        return [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'student_name' => $student?->full_name_en ?: $student?->full_name_kh,
            'student_name_kh' => $student?->full_name_kh,
            'student_code' => $student?->id_card_number,
            'student_type' => $student?->student_type,
            'student_tuition_plan' => $student?->tuition_plan,
            'student_tuition_plan_label' => StudentPaymentService::paymentPlanLabel($student?->tuition_plan),
            'major_name' => $academic?->major?->name,
            'year' => $academic?->stage,
            'shift_name' => $academic?->shift?->name,
            'invoice_no' => $this->resource->invoice_no,
            'academic_year' => $this->resource->academic_year,
            'term' => $this->resource->term,
            'payment_plan' => $this->resource->payment_plan,
            'payment_plan_label' => StudentPaymentService::paymentPlanLabel($this->resource->payment_plan) ?? $this->resource->payment_plan,
            'payable_amount' => max(0, (float) $this->resource->amount_due - (float) $this->resource->discount),
            'payment_type' => $this->resource->payment_type,
            'amount_due' => (float) $this->resource->amount_due,
            'discount' => (float) $this->resource->discount,
            'amount_paid' => (float) $this->resource->amount_paid,
            'balance' => $this->resource->balance,
            'due_date' => $this->resource->due_date?->format('Y-m-d'),
            'paid_at' => $this->resource->paid_at?->format('Y-m-d'),
            'status' => $this->resource->status,
            'payment_method' => $this->resource->payment_method,
            'reference_no' => $this->resource->reference_no,
            'note' => $this->resource->note,
            'created_at' => $this->resource->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->resource->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
