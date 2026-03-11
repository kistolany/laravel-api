<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Services\StudentCardService;

class StudentCardController extends Controller
{
    public function __construct(
        protected StudentCardService $service
    ) {}

    public function show(int $student_id)
    {
        $response = $this->service->buildStudentCardResponse($student_id);

        return response()->json($response['payload'], $response['status']);
    }

    public function byMajor(string $major)
    {
        $response = $this->service->buildMajorCardsResponse($major);

        return response()->json($response['payload'], $response['status']);
    }
}
