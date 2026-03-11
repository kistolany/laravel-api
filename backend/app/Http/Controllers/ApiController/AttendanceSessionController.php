<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceRecordBulkRequest;
use App\Http\Requests\AttendanceSessionRequest;
use App\Services\AttendanceSessionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class AttendanceSessionController extends Controller
{
     use ApiResponseTrait;
    public function __construct(
        protected AttendanceSessionService $service
    ) {}

    public function show(int $id): JsonResponse
    {
        $response = $this->service->buildDetailResponse($id);

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

    public function store(AttendanceSessionRequest $request): JsonResponse
    {
        $response = $this->service->createSessionResponse($request->validated());

        return response()->json($response['payload'], $response['status']);
    }
    

    public function record(AttendanceRecordBulkRequest $request, int $id): JsonResponse
    {
        $response = $this->service->recordAttendanceResponse($id, $request->validated());

        return response()->json($response['payload'], $response['status']);
    }
}
