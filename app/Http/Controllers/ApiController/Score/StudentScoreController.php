<?php

namespace App\Http\Controllers\ApiController\Score;

use App\Http\Controllers\Controller;
use App\Http\Requests\Score\StudentScoreBulkUpsertRequest;
use App\Services\Score\StudentScoreService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentScoreController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected StudentScoreService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->service->index($request->query()), 'Student scores retrieved successfully.');
    }

    public function bulkUpsert(StudentScoreBulkUpsertRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->success(
            $this->service->bulkUpsert($validated['scores']),
            'Student scores saved successfully.'
        );
    }
}
