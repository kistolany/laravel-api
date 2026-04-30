<?php

namespace App\Http\Controllers\ApiController\AcademicInfo;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicInfo\AcademicInfoRequest;
use App\Http\Resources\AcademicInfo\AcademicInfoResource;
use App\Services\AcademicInfo\AcademicInfoService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class AcademicInfoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private AcademicInfoService $service
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->index());
    }

    public function store(AcademicInfoRequest $request): JsonResponse
    {
        return $this->success(
            new AcademicInfoResource($this->service->create($request->validated())),
            'Academic info created successfully.'
        );
    }

    public function show($id): JsonResponse
    {
        return $this->success(new AcademicInfoResource($this->service->findById((int) $id)));
    }

    public function update(AcademicInfoRequest $request, $id): JsonResponse
    {
        return $this->success(
            new AcademicInfoResource($this->service->update((int) $id, $request->validated())),
            'Academic info updated successfully.'
        );
    }

    public function destroy($id): JsonResponse
    {
        $this->service->delete((int) $id);

        return $this->success(null, 'Academic info deleted successfully.');
    }

    public function getByMajorId(int $majorId): JsonResponse
    {
        return $this->success(AcademicInfoResource::collection($this->service->byMajor($majorId)));
    }

    public function getByShiftId(int $shiftId): JsonResponse
    {
        return $this->success(AcademicInfoResource::collection($this->service->byShift($shiftId)));
    }
}
