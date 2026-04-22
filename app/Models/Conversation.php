<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Conversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // ── Scopes ───────────────────────────────────────────────────

    /**
     * Scope to get conversations where the given user is a participant.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_one_id', $userId)
                     ->orWhere('user_two_id', $userId);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Check if a user is a participant in this conversation.
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->user_one_id === $userId || $this->user_two_id === $userId;
    }

    /**
     * Get the other participant's user ID.
     */
    public function getOtherUserId(int $authUserId): int
    {
        return $this->user_one_id === $authUserId
            ? $this->user_two_id
            : $this->user_one_id;
    }
}
