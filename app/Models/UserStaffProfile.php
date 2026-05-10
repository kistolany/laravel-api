<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStaffProfile extends Model
{
    protected $table = 'user_staff_profiles';

    protected $fillable = [
        'user_id',
        'department',
        'position',
        'join_date',
        'base_salary',
        'allowance',
        'bank_name',
        'bank_account',
    ];

    protected function casts(): array
    {
        return [
            'join_date' => 'date',
            'base_salary' => 'decimal:2',
            'allowance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
