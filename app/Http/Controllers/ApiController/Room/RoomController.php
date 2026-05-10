<?php

namespace App\Http\Controllers\ApiController\Room;

use App\Http\Controllers\Controller;
use App\Http\Requests\Room\RoomRequest;
use App\Models\Room;
use App\Traits\ApiResponseTrait;
use App\Enums\ResponseMessage;
use App\Enums\ResponseStatus;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    use ApiResponseTrait;

    public function index(): JsonResponse
    {
        $rooms = Room::query()
            ->when(request('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return $this->success($rooms, ResponseMessage::SUCCESS);
    }

    public function store(RoomRequest $request): JsonResponse
    {
        $room = Room::create($request->validated());

        return $this->success($room, ResponseMessage::CREATED, ResponseStatus::CREATED);
    }

    public function show(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);

        return $this->success($room, ResponseMessage::SUCCESS);
    }

    public function update(RoomRequest $request, int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->update($request->validated());

        return $this->success($room, ResponseMessage::SUCCESS);
    }

    public function destroy(int $id): JsonResponse
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return $this->success(null, ResponseMessage::SUCCESS);
    }
}
