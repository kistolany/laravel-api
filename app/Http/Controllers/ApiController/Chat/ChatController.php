<?php

namespace App\Http\Controllers\ApiController\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ChatController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────

    private function success(mixed $data, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'datetime'  => now()->toIso8601String(),
            'timestamp' => now()->timestamp,
            'status'    => true,
            'code'      => $code,
            'message'   => $message,
            'data'      => $data,
        ], $code);
    }

    private function error(string $message, int $code = 400, mixed $data = null): JsonResponse
    {
        return response()->json([
            'datetime'  => now()->toIso8601String(),
            'timestamp' => now()->timestamp,
            'status'    => false,
            'code'      => $code,
            'message'   => $message,
            'data'      => $data,
        ], $code);
    }

    /**
     * Format a user object for chat responses.
     */
    private function formatUser(User $user): array
    {
        return [
            'id'        => $user->id,
            'username'  => $user->username,
            'full_name' => $user->full_name ?? $user->username,
            'image'     => $user->image ?? '',
            'status'    => $user->status ?? 'Active',
            'role'      => $user->role?->name ?? '',
        ];
    }

    // ── GET /chat/users ─────────────────────────────────────────

    /**
     * List users available for chat (excludes current user).
     * Supports ?search= query parameter.
     */
    public function users(Request $request): JsonResponse
    {
        $authId = $request->user()->id;
        $search = $request->query('search', '');

        $query = User::with('role')
            ->where('id', '!=', $authId)
            ->where('status', 'Active');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('full_name')
                       ->limit(50)
                       ->get()
                       ->map(fn (User $u) => $this->formatUser($u));

        return $this->success($users);
    }

    // ── GET /chat/conversations ─────────────────────────────────

    /**
     * List authenticated user's conversations, with last message & unread count.
     */
    public function conversations(Request $request): JsonResponse
    {
        $authId = $request->user()->id;

        $conversations = Conversation::forUser($authId)
            ->with(['userOne:id,username,full_name,image,status', 'userTwo:id,username,full_name,image,status', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Conversation $conv) use ($authId) {
                $otherUser = $conv->user_one_id === $authId ? $conv->userTwo : $conv->userOne;
                $unreadCount = $conv->messages()->unreadFor($authId)->count();

                return [
                    'id'             => $conv->id,
                    'other_user'     => $otherUser ? [
                        'id'        => $otherUser->id,
                        'username'  => $otherUser->username,
                        'full_name' => $otherUser->full_name ?? $otherUser->username,
                        'image'     => $otherUser->image ?? '',
                        'status'    => $otherUser->status ?? 'Active',
                    ] : null,
                    'last_message'   => $conv->latestMessage ? [
                        'id'         => $conv->latestMessage->id,
                        'body'       => $conv->latestMessage->body,
                        'sender_id'  => $conv->latestMessage->sender_id,
                        'created_at' => $conv->latestMessage->created_at?->toIso8601String(),
                    ] : null,
                    'unread_count'   => $unreadCount,
                    'last_message_at' => $conv->last_message_at?->toIso8601String(),
                    'created_at'     => $conv->created_at?->toIso8601String(),
                ];
            });

        return $this->success($conversations);
    }

    // ── POST /chat/conversations ────────────────────────────────

    /**
     * Find or create a conversation with another user.
     */
    public function findOrCreate(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $authId  = $request->user()->id;
        $otherId = (int) $request->input('user_id');

        if ($authId === $otherId) {
            return $this->error('Cannot start a conversation with yourself.', 422);
        }

        // Normalize ordering so (1,2) and (2,1) map to the same row
        $userOneId = min($authId, $otherId);
        $userTwoId = max($authId, $otherId);

        $conversation = Conversation::firstOrCreate(
            ['user_one_id' => $userOneId, 'user_two_id' => $userTwoId],
        );

        $conversation->load(['userOne:id,username,full_name,image,status', 'userTwo:id,username,full_name,image,status']);

        $otherUser = $conversation->user_one_id === $authId
            ? $conversation->userTwo
            : $conversation->userOne;

        return $this->success([
            'id'         => $conversation->id,
            'other_user' => $otherUser ? [
                'id'        => $otherUser->id,
                'username'  => $otherUser->username,
                'full_name' => $otherUser->full_name ?? $otherUser->username,
                'image'     => $otherUser->image ?? '',
                'status'    => $otherUser->status ?? 'Active',
            ] : null,
            'created_at' => $conversation->created_at?->toIso8601String(),
        ], 'Conversation ready.');
    }

    // ── GET /chat/conversations/{id}/messages ───────────────────

    /**
     * Paginated messages for a conversation. Privacy-enforced.
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $authId = $request->user()->id;

        $conversation = Conversation::find($id);

        if (!$conversation || !$conversation->hasParticipant($authId)) {
            return $this->error('Conversation not found.', 404);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);

        $messages = $conversation->messages()
            ->with('sender:id,username,full_name,image')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = collect($messages->items())->map(fn (Message $msg) => [
            'id'         => $msg->id,
            'body'       => $msg->body,
            'sender_id'  => $msg->sender_id,
            'sender'     => $msg->sender ? [
                'id'        => $msg->sender->id,
                'username'  => $msg->sender->username,
                'full_name' => $msg->sender->full_name ?? $msg->sender->username,
                'image'     => $msg->sender->image ?? '',
            ] : null,
            'read_at'    => $msg->read_at?->toIso8601String(),
            'created_at' => $msg->created_at?->toIso8601String(),
        ]);

        return $this->success([
            'items'      => $items,
            'pagination' => [
                'total'        => $messages->total(),
                'per_page'     => $messages->perPage(),
                'current_page' => $messages->currentPage(),
                'total_pages'  => $messages->lastPage(),
            ],
        ]);
    }

    // ── POST /chat/conversations/{id}/messages ──────────────────

    /**
     * Send a message in a conversation. Privacy-enforced.
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $authId = $request->user()->id;

        $conversation = Conversation::find($id);

        if (!$conversation || !$conversation->hasParticipant($authId)) {
            return $this->error('Conversation not found.', 404);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $authId,
            'body'      => $request->input('body'),
        ]);

        // Update last_message_at timestamp on the conversation
        $conversation->update(['last_message_at' => $message->created_at]);

        $message->load('sender:id,username,full_name,image');

        return $this->success([
            'id'         => $message->id,
            'body'       => $message->body,
            'sender_id'  => $message->sender_id,
            'sender'     => $message->sender ? [
                'id'        => $message->sender->id,
                'username'  => $message->sender->username,
                'full_name' => $message->sender->full_name ?? $message->sender->username,
                'image'     => $message->sender->image ?? '',
            ] : null,
            'read_at'    => null,
            'created_at' => $message->created_at?->toIso8601String(),
        ], 'Message sent.', 201);
    }

    // ── PATCH /chat/conversations/{id}/read ─────────────────────

    /**
     * Mark all unread messages in a conversation as read.
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $authId = $request->user()->id;

        $conversation = Conversation::find($id);

        if (!$conversation || !$conversation->hasParticipant($authId)) {
            return $this->error('Conversation not found.', 404);
        }

        $updated = $conversation->messages()
            ->unreadFor($authId)
            ->update(['read_at' => Carbon::now()]);

        return $this->success([
            'marked_count' => $updated,
        ], 'Messages marked as read.');
    }

    // ── GET /chat/unread-count ──────────────────────────────────

    /**
     * Total unread message count across all conversations.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $authId = $request->user()->id;

        $conversationIds = Conversation::forUser($authId)->pluck('id');

        $count = Message::whereIn('conversation_id', $conversationIds)
            ->unreadFor($authId)
            ->count();

        return $this->success([
            'unread_count' => $count,
        ]);
    }

    /**
     * Delete a message. Only the sender can delete their own message.
     */
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        $authId = $request->user()->id;
        $message = Message::find($messageId);

        if (!$message) {
            return $this->error('Message not found.', 404);
        }

        if ($message->sender_id !== $authId) {
            return $this->error('Unauthorized to delete this message.', 403);
        }

        $message->delete();

        return $this->success(null, 'Message deleted successfully.');
    }
}
