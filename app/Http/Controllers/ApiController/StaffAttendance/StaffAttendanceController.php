<?php

namespace App\Http\Controllers\ApiController\StaffAttendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffAttendance\StaffAttendanceBulkRequest;
use App\Http\Requests\StaffAttendance\StaffAttendanceIndexRequest;
use App\Http\Requests\StaffAttendance\StaffAttendanceRangeRequest;
use App\Http\Resources\StaffAttendance\StaffAttendanceHistoryResource;
use App\Http\Resources\StaffAttendance\StaffAttendanceIndexResource;
use App\Http\Resources\StaffAttendance\StaffAttendanceReportResource;
use App\Services\StaffAttendance\StaffAttendanceService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StaffAttendanceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private StaffAttendanceService $service
    ) {}

    public function index(StaffAttendanceIndexRequest $request): JsonResponse
    {
        return $this->success(
            new StaffAttendanceIndexResource($this->service->index($request->validated()))
        );
    }

    public function bulk(StaffAttendanceBulkRequest $request): JsonResponse
    {
        return $this->success(
            new StaffAttendanceIndexResource($this->service->bulk($request->validated(), Auth::id()))
        );
    }

    public function history(StaffAttendanceRangeRequest $request): JsonResponse
    {
        return $this->success(
            new StaffAttendanceHistoryResource($this->service->history($request->validated()))
        );
    }

    public function report(StaffAttendanceRangeRequest $request): JsonResponse
    {
        return $this->success(
            new StaffAttendanceReportResource($this->service->report($request->validated()))
        );
    }
}
