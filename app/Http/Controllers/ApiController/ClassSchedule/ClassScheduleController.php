<?php

namespace App\Http\Controllers\ApiController\ClassSchedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassSchedule\ClassScheduleAutoGenerateConfirmRequest;
use App\Http\Requests\ClassSchedule\ClassScheduleAutoGenerateRequest;
use App\Http\Requests\ClassSchedule\ClassScheduleRequest;
use App\Http\Resources\ClassSchedule\ClassScheduleResource;
use App\Services\ClassSchedule\ClassScheduleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ClassScheduleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ClassScheduleService $service
    ) {}

    public function index(): JsonResponse
    {
        return $this->success($this->service->index());
    }

    public function store(ClassScheduleRequest $request): JsonResponse
    {
        $schedule = $this->service->create($request->validated());

        return $this->success(new ClassScheduleResource($schedule), 'Class schedule created successfully!');
    }

    public function show($id): JsonResponse
    {
        $schedule = $this->service->findById((int) $id);

        return $this->success(new ClassScheduleResource($schedule));
    }

    public function update(ClassScheduleRequest $request, $id): JsonResponse
    {
        $schedule = $this->service->update((int) $id, $request->validated());

        return $this->success(new ClassScheduleResource($schedule), 'Class schedule updated successfully!');
    }

    public function destroy($id): JsonResponse
    {
        $this->service->delete((int) $id);

        return $this->success(null, 'Class schedule deleted successfully!');
    }

    public function byClass($classId): JsonResponse
    {
        return $this->success($this->service->getByClass((int) $classId));
    }

    public function autoGenerate(ClassScheduleAutoGenerateRequest $request): JsonResponse
    {
        return $this->success(
            $this->service->autoGeneratePreview($request->validated('slots')),
            'Auto-generate preview ready.'
        );
    }

    public function autoGenerateConfirm(ClassScheduleAutoGenerateConfirmRequest $request): JsonResponse
    {
        $created = $this->service->autoGenerateConfirm($request->validated('schedules'));

        return $this->success(
            ClassScheduleResource::collection($created),
            count($created) . ' schedules created successfully.'
        );
    }
}
