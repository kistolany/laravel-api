<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPayment extends Model
{
    protected $fillable = [
        'student_id',
        'invoice_no',
        'academic_year',
        'term',
        'payment_plan',
        'payment_type',
        'amount_due',
        'discount',
        'amount_paid',
        'due_date',
        'paid_at',
        'status',
        'payment_method',
        'reference_no',
        'note',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'amount_due' => 'decimal:2',
        'discount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function getBalanceAttribute(): float
    {
        $total = (float) $this->amount_due - (float) $this->discount;

        return max(0, round($total - (float) $this->amount_paid, 2));
    }
}
