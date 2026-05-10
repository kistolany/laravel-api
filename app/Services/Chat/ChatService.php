<?php

namespace App\Services\Chat;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ChatService extends BaseService
{
    private function isOnline(int $userId): bool
    {
        // Check both system user and teacher cache keys
        return Cache::has('user-online-user-' . $userId) || Cache::has('user-online-teacher-' . $userId);
    }
    public function users(User $user, array $filters = []): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($user, $filters): Collection {
            $search = $filters['search'] ?? null;

            return User::with('role')
                ->where('id', '!=', $user->id)
                ->where('status', 'Active')
                ->when($search, function ($query, string $search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
                })
                ->orderBy('full_name')
                ->limit(50)
                ->get()
                ->map(fn (User $chatUser): array => $this->formatUser($chatUser))
                ->values();
        });
    }

    public function conversations(User $user): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($user): Collection {
            return Conversation::forUser($user->id)
                ->with(['userOne:id,username,full_name,image,status', 'userTwo:id,username,full_name,image,status'])
                ->orderByDesc('last_message_at')
                ->get()
                ->map(fn (Conversation $conversation): array => $this->formatConversation($conversation, $user->id))
                ->values();
        });
    }

    public function findOrCreate(User $user, int $otherId): array
    {
        return $this->trace(__FUNCTION__, function () use ($user, $otherId): array {
            if ($user->id === $otherId) {
                throw new ApiException(ResponseStatus::EXISTING_DATA, 'Cannot start a conversation with yourself.');
            }

            $conversation = Conversation::firstOrCreate([
                'user_one_id' => min($user->id, $otherId),
                'user_two_id' => max($user->id, $otherId),
            ]);

            $conversation->load(['userOne:id,username,full_name,image,status', 'userTwo:id,username,full_name,image,status']);

            return $this->formatConversationSummary($conversation, $user->id);
        });
    }

    public function messages(User $user, int $conversationId, array $filters = []): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function () use ($user, $conversationId, $filters): PaginatedResult {
            $conversation = $this->findConversationForUser($conversationId, $user->id);
            $perPage = min((int) ($filters['per_page'] ?? 50), 100);

            $query = $conversation->messages()
                ->with('sender:id,username,full_name,image')
                ->orderByDesc('created_at');

            if ($lastCleared = $this->lastClearedAt($conversation, $user->id)) {
                $query->where('created_at', '>', $lastCleared);
            }

            $messages = $query->paginate($perPage);

            return new PaginatedResult(
                items: collect($messages->items())->map(fn (Message $message): array => $this->formatMessage($message))->values(),
                total: $messages->total(),
                perPage: $messages->perPage(),
                currentPage: $messages->currentPage(),
                totalPages: $messages->lastPage()
            );
        });
    }

    public function sendMessage(User $user, int $conversationId, array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($user, $conversationId, $data): array {
            $conversation = $this->findConversationForUser($conversationId, $user->id);
            $message = $conversation->messages()->create([
                'sender_id' => $user->id,
                'body' => $data['body'],
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);
            $message->load('sender:id,username,full_name,image');

            return $this->formatMessage($message);
        });
    }

    public function markRead(User $user, int $conversationId): array
    {
        return $this->trace(__FUNCTION__, function () use ($user, $conversationId): array {
            $conversation = $this->findConversationForUser($conversationId, $user->id);
            $updated = $conversation->messages()
                ->unreadFor($user->id)
                ->update(['read_at' => Carbon::now()]);

            return ['marked_count' => $updated];
        });
    }

    public function unreadCount(User $user): array
    {
        return $this->trace(__FUNCTION__, function () use ($user): array {
            $conversationIds = Conversation::forUser($user->id)->pluck('id');

            return [
                'unread_count' => Message::whereIn('conversation_id', $conversationIds)
                    ->unreadFor($user->id)
                    ->count(),
            ];
        });
    }

    public function deleteMessage(User $user, int $messageId): void
    {
        $this->trace(__FUNCTION__, function () use ($user, $messageId): void {
            $message = Message::find($messageId);

            if (!$message) {
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Message not found.');
            }

            if ($message->sender_id !== $user->id && !$user->hasRole('Admin')) {
                throw new ApiException(ResponseStatus::FORBIDDEN, 'Unauthorized to delete this message.');
            }

            $message->delete();
        });
    }

    public function clearConversation(User $user, int $conversationId): void
    {
        $this->trace(__FUNCTION__, function () use ($user, $conversationId): void {
            $conversation = $this->findConversationForUser($conversationId, $user->id);
            $field = $conversation->user_one_id === $user->id
                ? 'user_one_last_cleared_at'
                : 'user_two_last_cleared_at';

            $conversation->forceFill([$field => now()])->save();
        });
    }

    public function destroyConversation(User $user, int $conversationId): void
    {
        $this->trace(__FUNCTION__, function () use ($user, $conversationId): void {
            $this->findConversationForUser($conversationId, $user->id)->delete();
        });
    }

    public function toggleMute(User $user, int $conversationId): array
    {
        return $this->trace(__FUNCTION__, function () use ($user, $conversationId): array {
            $conversation = $this->findConversationForUser($conversationId, $user->id);
            $field = $conversation->user_one_id === $user->id ? 'user_one_muted' : 'user_two_muted';
            $conversation->forceFill([$field => !$conversation->{$field}])->save();

            return ['is_muted' => (bool) $conversation->{$field}];
        });
    }

    private function findConversationForUser(int $conversationId, int $userId): Conversation
    {
        $conversation = Conversation::find($conversationId);

        if (!$conversation || !$conversation->hasParticipant($userId)) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Conversation not found.');
        }

        return $conversation;
    }

    private function formatConversation(Conversation $conversation, int $userId): array
    {
        $lastCleared = $this->lastClearedAt($conversation, $userId);
        $unreadQuery = $conversation->messages()->unreadFor($userId);
        $latestQuery = $conversation->messages()->orderByDesc('created_at');

        if ($lastCleared) {
            $unreadQuery->where('created_at', '>', $lastCleared);
            $latestQuery->where('created_at', '>', $lastCleared);
        }

        return [
            ...$this->formatConversationSummary($conversation, $userId),
            'last_message' => ($latestMessage = $latestQuery->first()) ? [
                'id' => $latestMessage->id,
                'body' => $latestMessage->body,
                'sender_id' => $latestMessage->sender_id,
                'created_at' => $latestMessage->created_at?->toIso8601String(),
            ] : null,
            'unread_count' => $unreadQuery->count(),
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    private function formatConversationSummary(Conversation $conversation, int $userId): array
    {
        $otherUser = $conversation->user_one_id === $userId ? $conversation->userTwo : $conversation->userOne;

        return [
            'id' => $conversation->id,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'username' => $otherUser->username,
                'full_name' => $otherUser->full_name ?? $otherUser->username,
                'image' => $otherUser->image ?? '',
                'status' => $otherUser->status ?? 'Active',
                'is_online' => $this->isOnline($otherUser->id),
            ] : null,
            'is_muted' => $conversation->user_one_id === $userId
                ? (bool) $conversation->user_one_muted
                : (bool) $conversation->user_two_muted,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }

    private function formatMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'sender_id' => $message->sender_id,
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'username' => $message->sender->username,
                'full_name' => $message->sender->full_name ?? $message->sender->username,
                'image' => $message->sender->image ?? '',
            ] : null,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name ?? $user->username,
            'image' => $user->image ?? '',
            'status' => $user->status ?? 'Active',
            'is_online' => $this->isOnline($user->id),
            'role' => $user->role?->name ?? '',
        ];
    }

    private function lastClearedAt(Conversation $conversation, int $userId): mixed
    {
        return $conversation->user_one_id === $userId
            ? $conversation->user_one_last_cleared_at
            : $conversation->user_two_last_cleared_at;
    }
}
