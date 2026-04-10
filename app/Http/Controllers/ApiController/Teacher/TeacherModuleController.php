<?php

namespace App\Http\Controllers\ApiController\Teacher;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceRecordBulkRequest;
use App\Http\Requests\Attendance\AttendanceSessionRequest;
use App\Http\Resources\Teacher\TeacherStudentResource;
use App\Services\Attendance\AttendanceSessionService;
use App\Services\Teacher\TeacherModuleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherModuleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TeacherModuleService $service,
        private AttendanceSessionService $attendanceService
    ) {
    }

    public function students(Request $request): JsonResponse
    {
        return $this->success($this->service->students($request->user()), 'Students retrieved successfully.');
    }

    public function classes(Request $request): JsonResponse
    {
        return $this->success($this->service->classes($request->user()), 'Classes retrieved successfully.');
    }

    public function classStudents(Request $request, int $id): JsonResponse
    {
        $students = $this->service->classStudents($request->user(), $id);

        return $this->success(TeacherStudentResource::collection($students), 'Class students retrieved successfully.');
    }

    public function attendanceOptions(Request $request): JsonResponse
    {
        $request->user()->loadMissing(['major', 'subject']);

        return $this->success(
            $this->service->attendanceOptions($request->user()),
            'Attendance options retrieved successfully.'
        );
    }

    public function attendanceHistory(Request $request): JsonResponse
    {
        return $this->success(
            $this->service->attendanceHistory($request->user()),
            'Attendance history retrieved successfully.'
        );
    }

    public function attendanceShow(Request $request, int $id): JsonResponse
    {
        $response = $this->attendanceService->buildTeacherDetailResponse($request->user(), $id);

        return response()->json($response['payload'], $response['status']);
    }

    public function attendanceStore(AttendanceSessionRequest $request): JsonResponse
    {
        $response = $this->attendanceService->createTeacherSessionResponse(
            $request->user(),
            $request->validated()
        );

        return response()->json($response['payload'], $response['status']);
    }

    public function attendanceRecord(AttendanceRecordBulkRequest $request, int $id): JsonResponse
    {
        $response = $this->attendanceService->recordTeacherAttendanceResponse(
            $request->user(),
            $id,
            $request->validated()
        );

        return response()->json($response['payload'], $response['status']);
    }
}

