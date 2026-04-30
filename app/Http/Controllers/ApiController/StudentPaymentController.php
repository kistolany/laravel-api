<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentPayment\StudentPaymentRequest;
use App\Http\Resources\StudentPayment\StudentPaymentResource;
use App\Services\StudentPayment\StudentPaymentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class StudentPaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private StudentPaymentService $service)
    {
    }

    public function plans(): JsonResponse
    {
        return $this->success($this->service->plans(), 'Student payment plans retrieved successfully.');
    }

    public function index(StudentPaymentRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->list($request->validated()),
            'Student payments retrieved successfully.'
        );
    }

    public function store(StudentPaymentRequest $request): JsonResponse
    {
        return $this->success(
            new StudentPaymentResource($this->service->create($request->validated())),
            'Student payment created successfully.'
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->success(
            new StudentPaymentResource($this->service->get($id)),
            'Student payment retrieved successfully.'
        );
    }

    public function update(StudentPaymentRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new StudentPaymentResource($this->service->update($id, $request->validated())),
            'Student payment updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Student payment deleted successfully.');
    }
}
