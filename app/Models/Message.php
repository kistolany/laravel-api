<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    // ── Relations ────────────────────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    /**
     * Messages that are unread for the given user (not sent by them, and not yet read).
     */
    public function scopeUnreadFor(Builder $query, int $userId): Builder
    {
        return $query->where('sender_id', '!=', $userId)
                     ->whereNull('read_at');
    }
}
