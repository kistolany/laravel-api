<?php

namespace App\Http\Controllers\ApiController\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\StudentCardService;
use Illuminate\Http\Request;

class StudentCardController extends Controller
{
    public function __construct(
        protected StudentCardService $service
    ) {}

    public function index(Request $request)
    {
        $response = $this->service->buildCardListResponse($request->query());

        return response()->json($response['payload'], $response['status']);
    }

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
