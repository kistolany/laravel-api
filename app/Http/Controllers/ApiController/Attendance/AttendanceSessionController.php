<?php

namespace App\Http\Controllers\ApiController\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\AttendanceMatrixRequest;
use App\Http\Requests\Attendance\AttendanceRecordBulkRequest;
use App\Http\Requests\Attendance\AttendanceSessionRequest;
use App\Services\Attendance\AttendanceSessionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceSessionController extends Controller
{
     use ApiResponseTrait;
    public function __construct(
        protected AttendanceSessionService $service
    ) {}

    public function index(): JsonResponse
    {
        $teacherId = Auth::user()?->teacher_id;

        if ($teacherId) {
            $teacher = \App\Models\Teacher::find($teacherId);
            if (!$teacher) {
                return response()->json(['data' => []], 200);
            }
            $response = $this->service->buildTeacherListResponse($teacher);
        } else {
            $response = $this->service->buildListResponse();
        }

        return response()->json($response['payload'], $response['status']);
    }

    public function show(int $id): JsonResponse
    {
        $teacherId = Auth::user()?->teacher_id;

        if ($teacherId) {
            $teacher = \App\Models\Teacher::find($teacherId);
            if (!$teacher) {
                return response()->json(['message' => 'Teacher profile not found.'], 404);
            }
            $response = $this->service->buildTeacherDetailResponse($teacher, $id);
        } else {
            $response = $this->service->buildDetailResponse($id);
        }

        return response()->json($response['payload'], $response['status']);
    }

    public function byMajor(int $majorId): JsonResponse
    {
        $response = $this->service->buildMajorDetailResponse($majorId);

        return response()->json($response['payload'], $response['status']);
    }

    public function reportByMajorAndSubject(int $majorId, int $subjectId): JsonResponse
    {
        $response = $this->service->buildMajorSubjectReportResponse($majorId, $subjectId);

        return response()->json($response['payload'], $response['status']);
    }

    public function matrix(Request $request): JsonResponse
    {
        $response = $this->service->buildMatrixResponse($request->query());

        return response()->json($response['payload'], $response['status']);
    }

    public function saveMatrix(AttendanceMatrixRequest $request): JsonResponse
    {
        $response = $this->service->saveMatrixResponse($request->validated());

        return response()->json($response['payload'], $response['status']);
    }

    public function store(AttendanceSessionRequest $request): JsonResponse
    {
        $validated  = $request->validated();
        $teacherId  = Auth::user()?->teacher_id;

        // Force teacher_id to the authenticated teacher's own profile
        if ($teacherId) {
            $validated['teacher_id'] = $teacherId;
        }

        $response = $this->service->createSessionResponse($validated);

        return response()->json($response['payload'], $response['status']);
    }
    

    public function record(AttendanceRecordBulkRequest $request, int $id): JsonResponse
    {
        $response = $this->service->recordAttendanceResponse($id, $request->validated());

        return response()->json($response['payload'], $response['status']);
    }
}
