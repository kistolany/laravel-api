<?php

namespace App\Services\ClassSchedule;

use App\Services\BaseService;
use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\ClassSchedule\ClassScheduleResource;
use App\Models\ClassSchedule;
use Illuminate\Support\Facades\Log;

class ClassScheduleService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = ClassSchedule::with(['classroom', 'subject', 'teacher', 'shift'])->latest();

            return $this->paginateResponse($query, ClassScheduleResource::class);
        });
    }

    public function findById(int $id): ClassSchedule
    {
        return $this->trace(__FUNCTION__, function () use ($id): ClassSchedule {
            $schedule = ClassSchedule::with(['classroom', 'subject', 'teacher', 'shift'])->find($id);

            if (!$schedule) {
                Log::warning('ClassSchedule not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Class schedule with ID :$id not found.");
            }

            return $schedule;
        });
    }

    public function create(array $data): ClassSchedule
    {
        return $this->trace(__FUNCTION__, function () use ($data): ClassSchedule {
            $schedule = ClassSchedule::create($data);

            return $schedule->load(['classroom', 'subject', 'teacher', 'shift']);
        });
    }

    public function update(int $id, array $data): ClassSchedule
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): ClassSchedule {
            $schedule = $this->findById($id);

            $schedule->update($data);

            return $schedule->load(['classroom', 'subject', 'teacher', 'shift']);
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $schedule = $this->findById($id);

            return $schedule->delete();
        });
    }

    public function getByClass(int $classId): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function () use ($classId): PaginatedResult {
            $query = ClassSchedule::with(['classroom', 'subject', 'teacher', 'shift'])
                ->where('class_id', $classId)
                ->latest();

            return $this->paginateResponse($query, ClassScheduleResource::class);
        });
    }
}
