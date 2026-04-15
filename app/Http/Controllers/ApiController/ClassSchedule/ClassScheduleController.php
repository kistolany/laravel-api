<?php

namespace App\Http\Controllers\ApiController\ClassSchedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassSchedule\ClassScheduleRequest;
use App\Http\Resources\ClassSchedule\ClassScheduleResource;
use App\Services\ClassSchedule\ClassScheduleService;
use App\Traits\ApiResponseTrait;

class ClassScheduleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ClassScheduleService $service
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(ClassScheduleRequest $request)
    {
        $schedule = $this->service->create($request->validated());

        return $this->success(new ClassScheduleResource($schedule), 'Class schedule created successfully!');
    }

    public function show($id)
    {
        $schedule = $this->service->findById((int) $id);

        return $this->success(new ClassScheduleResource($schedule));
    }

    public function update(ClassScheduleRequest $request, $id)
    {
        $schedule = $this->service->update((int) $id, $request->validated());

        return $this->success(new ClassScheduleResource($schedule), 'Class schedule updated successfully!');
    }

    public function destroy($id)
    {
        $this->service->delete((int) $id);

        return $this->success(null, 'Class schedule deleted successfully!');
    }

    public function byClass($classId)
    {
        return $this->success($this->service->getByClass((int) $classId));
    }
}
