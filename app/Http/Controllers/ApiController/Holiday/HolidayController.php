<?php

namespace App\Http\Controllers\ApiController\Holiday;

use App\Http\Controllers\Controller;
use App\Http\Requests\Holiday\HolidayRequest;
use App\Http\Resources\Holiday\HolidayResource;
use App\Services\Holiday\HolidayService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HolidayController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private HolidayService $service)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success(
            HolidayResource::collection($this->service->list()),
            'Holidays retrieved successfully.'
        );
    }

    public function publicIndex(): JsonResponse
    {
        return $this->success(
            HolidayResource::collection($this->service->list()),
            'Holidays retrieved successfully.'
        );
    }

    public function store(HolidayRequest $request): JsonResponse
    {
        return $this->success(
            new HolidayResource($this->service->create(
                $request->validated(),
                $request->file('document'),
                $request->user()
            )),
            'Holiday created successfully.'
        );
    }

    public function update(HolidayRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new HolidayResource($this->service->update(
                $id,
                $request->validated(),
                $request->file('document'),
                $request->user()
            )),
            'Holiday updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Holiday deleted successfully.');
    }

    public function downloadDocument(int $id): BinaryFileResponse
    {
        return $this->service->documentResponse($id);
    }
}
