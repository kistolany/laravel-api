<?php

namespace App\Http\Controllers\ApiController\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ChatActionRequest;
use App\Http\Requests\Chat\ChatConversationRequest;
use App\Http\Requests\Chat\ChatMessageListRequest;
use App\Http\Requests\Chat\ChatSendMessageRequest;
use App\Http\Requests\Chat\ChatUserSearchRequest;
use App\Http\Resources\Chat\ChatResource;
use App\Services\Chat\ChatService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ChatService $service
    ) {}

    public function users(ChatUserSearchRequest $request): JsonResponse
    {
        return $this->success(ChatResource::collection($this->service->users($request->user(), $request->validated())));
    }

    public function conversations(ChatActionRequest $request): JsonResponse
    {
        return $this->success(ChatResource::collection($this->service->conversations($request->user())));
    }

    public function findOrCreate(ChatConversationRequest $request): JsonResponse
    {
        return $this->success(
            new ChatResource($this->service->findOrCreate($request->user(), $request->validated('user_id'))),
            'Conversation ready.'
        );
    }

    public function messages(ChatMessageListRequest $request, int $id): JsonResponse
    {
        return $this->success($this->service->messages($request->user(), $id, $request->validated()));
    }

    public function sendMessage(ChatSendMessageRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new ChatResource($this->service->sendMessage($request->user(), $id, $request->validated())),
            'Message sent.'
        );
    }

    public function markRead(ChatActionRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new ChatResource($this->service->markRead($request->user(), $id)),
            'Messages marked as read.'
        );
    }

    public function unreadCount(ChatActionRequest $request): JsonResponse
    {
        return $this->success(new ChatResource($this->service->unreadCount($request->user())));
    }

    public function deleteMessage(ChatActionRequest $request, int $messageId): JsonResponse
    {
        $this->service->deleteMessage($request->user(), $messageId);

        return $this->success(null, 'Message deleted successfully.');
    }

    public function clearConversation(ChatActionRequest $request, int $id): JsonResponse
    {
        $this->service->clearConversation($request->user(), $id);

        return $this->success(null, 'Conversation cleared for you.');
    }

    public function destroyConversation(ChatActionRequest $request, int $id): JsonResponse
    {
        $this->service->destroyConversation($request->user(), $id);

        return $this->success(null, 'Conversation deleted.');
    }

    public function toggleMute(ChatActionRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new ChatResource($this->service->toggleMute($request->user(), $id)),
            'Mute status toggled.'
        );
    }
}
