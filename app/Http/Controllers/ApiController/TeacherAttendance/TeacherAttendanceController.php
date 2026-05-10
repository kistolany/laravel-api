<?php

namespace App\Http\Controllers\ApiController\TeacherAttendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherAttendance\TeacherAttendanceBulkRequest;
use App\Http\Requests\TeacherAttendance\TeacherAttendanceIndexRequest;
use App\Http\Requests\TeacherAttendance\TeacherAttendanceRangeRequest;
use App\Http\Resources\TeacherAttendance\TeacherAttendanceHistorySummaryResource;
use App\Http\Resources\TeacherAttendance\TeacherAttendanceIndexResource;
use App\Http\Resources\TeacherAttendance\TeacherAttendanceReportResource;
use App\Services\TeacherAttendance\TeacherAttendanceService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeacherAttendanceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TeacherAttendanceService $service
    ) {}

    public function index(TeacherAttendanceIndexRequest $request): JsonResponse
    {
        return $this->success(
            new TeacherAttendanceIndexResource($this->service->index($request->validated(), $request->user()))
        );
    }

    public function bulk(TeacherAttendanceBulkRequest $request): JsonResponse
    {
        return $this->success(
            new TeacherAttendanceIndexResource(
                $this->service->bulk($request->validated(), $request->user(), Auth::id())
            )
        );
    }

    public function history(TeacherAttendanceRangeRequest $request): JsonResponse
    {
        return $this->success(
            new TeacherAttendanceHistorySummaryResource($this->service->history($request->validated()))
        );
    }

    public function report(TeacherAttendanceRangeRequest $request): JsonResponse
    {
        return $this->success(
            new TeacherAttendanceReportResource($this->service->report($request->validated(), $request->user()))
        );
    }

    public function weekly(TeacherAttendanceRangeRequest $request): JsonResponse
    {
        return $this->success($this->service->weekly($request->validated(), $request->user()));
    }
}
