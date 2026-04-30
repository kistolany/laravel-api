<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveRequest\LeaveRequestRequest;
use App\Http\Resources\LeaveRequest\LeaveRequestResource;
use App\Services\LeaveRequest\LeaveRequestService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class LeaveRequestController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private LeaveRequestService $service)
    {
    }

    public function index(LeaveRequestRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->list($request->validated(), $request->user()),
            'Leave requests retrieved successfully.'
        );
    }

    public function store(LeaveRequestRequest $request): JsonResponse
    {
        return $this->success(
            new LeaveRequestResource($this->service->create($request->validated(), $request->user())),
            'Created successfully'
        );
    }

    public function updateStatus(LeaveRequestRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new LeaveRequestResource($this->service->updateStatus($id, $request->validated(), $request->user())),
            'Status updated successfully'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Deleted successfully');
    }
}
