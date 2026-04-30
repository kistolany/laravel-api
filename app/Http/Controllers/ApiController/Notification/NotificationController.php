<?php

namespace App\Http\Controllers\ApiController\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationRequest;
use App\Http\Resources\Notification\NotificationResource;
use App\Services\Notification\NotificationService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private NotificationService $service)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success(
            NotificationResource::collection($this->service->list()),
            'Notifications retrieved successfully.'
        );
    }

    public function store(NotificationRequest $request): JsonResponse
    {
        return $this->success(
            new NotificationResource($this->service->create($request->validated(), $request->user())),
            'Notification sent successfully.'
        );
    }

    public function feed(NotificationRequest $request): JsonResponse
    {
        return $this->success(
            NotificationResource::collection($this->service->feed($request->validated(), $request->user())),
            'Feed retrieved successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Notification deleted successfully.');
    }
}
