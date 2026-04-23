<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'password_hash',
        'role_id',
        'student_id',
        'teacher_id',
        'staff_id',
        'account_purpose',
        'status',
        'full_name',
        'image',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'student_id' => 'integer',
            'teacher_id' => 'integer',
            'staff_id' => 'string',
            'account_purpose' => 'string',
            'status' => 'string',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'student_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function hasRole(string $role): bool
    {
        $name = $this->role?->name;

        return $name !== null && strcasecmp($name, $role) === 0;
    }

    public function hasPermission(string $permission): bool
    {
        // Permissions are attached to the user's role.
        $this->loadMissing('role.permissions');

        if (!$this->role) {
            return false;
        }

        return $this->role->permissions->contains(
            fn (Permission $perm) => strcasecmp($perm->name, $permission) === 0
        );
    }

    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }
}
