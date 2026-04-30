<?php

namespace App\Http\Controllers\ApiController\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherAvailabilityRequest;
use App\Http\Resources\Teacher\TeacherAvailabilityResource;
use App\Http\Resources\Teacher\TeacherAvailabilitySummaryResource;
use App\Services\Teacher\TeacherAvailabilityService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAvailabilityController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private TeacherAvailabilityService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success(
            TeacherAvailabilityResource::collection(
                $this->service->list($request->user(), $request->attributes->get('auth_guard'))
            ),
            'Availabilities retrieved.'
        );
    }

    public function byTeacher(int $teacherId): JsonResponse
    {
        return $this->success(
            TeacherAvailabilityResource::collection($this->service->byTeacher($teacherId)),
            'Availability retrieved.'
        );
    }

    public function allSummary(): JsonResponse
    {
        return $this->success(
            TeacherAvailabilitySummaryResource::collection($this->service->summary()),
            'Summary retrieved.'
        );
    }

    public function sync(TeacherAvailabilityRequest $request): JsonResponse
    {
        return $this->success(
            TeacherAvailabilityResource::collection($this->service->sync($request->validated())),
            'Availability saved successfully.'
        );
    }
}
