<?php

namespace App\Http\Controllers\ApiController\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardStatsRequest;
use App\Http\Requests\Dashboard\TeacherDashboardStatsRequest;
use App\Http\Resources\Dashboard\DashboardStatsResource;
use App\Http\Resources\Dashboard\TeacherDashboardStatsResource;
use App\Services\Dashboard\DashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected DashboardService $service
    ) {}

    public function teacherStats(TeacherDashboardStatsRequest $request): JsonResponse
    {
        return $this->success(
            new TeacherDashboardStatsResource($this->service->teacherStats($request->user()))
        );
    }

    public function stats(DashboardStatsRequest $request): JsonResponse
    {
        return $this->success(
            new DashboardStatsResource($this->service->stats($request->validated('period')))
        );
    }
}
