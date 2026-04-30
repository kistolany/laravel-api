<?php

namespace App\Services\ClassSchedule;

use App\Services\BaseService;
use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\ClassSchedule\ClassScheduleResource;
use App\Models\ClassSchedule;
use App\Models\TeacherAvailability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    public function autoGeneratePreview(array $slots): array
    {
        return $this->trace(__FUNCTION__, function () use ($slots): array {
            $allAvailability = TeacherAvailability::with(['teacher'])->get();
            $assigned = [];
            $scheduled = [];
            $conflicts = [];
            $unmatched = [];

            foreach ($slots as $slot) {
                $candidates = $allAvailability->filter(
                    fn ($availability) =>
                        $availability->subject_id == $slot['subject_id']
                        && $availability->shift_id == $slot['shift_id']
                        && $availability->day_of_week == $slot['day_of_week']
                );

                if ($candidates->isEmpty()) {
                    $unmatched[] = $slot;
                    continue;
                }

                $chosenAvailability = $candidates->first(
                    fn ($availability) => !isset($assigned[$this->busyKey($availability->teacher_id, $slot)])
                );
                $isConflict = $chosenAvailability === null;
                $chosenAvailability ??= $candidates->first();

                $assigned[$this->busyKey($chosenAvailability->teacher_id, $slot)] = true;
                $row = [
                    ...$slot,
                    'teacher_id' => $chosenAvailability->teacher_id,
                    'teacher_name' => trim($chosenAvailability->teacher->first_name . ' ' . $chosenAvailability->teacher->last_name),
                ];

                if ($isConflict) {
                    $conflicts[] = [
                        ...$row,
                        'conflict_reason' => 'Teacher already assigned on this day & shift',
                    ];
                    continue;
                }

                $scheduled[] = $row;
            }

            return compact('scheduled', 'conflicts', 'unmatched');
        });
    }

    public function autoGenerateConfirm(array $schedules)
    {
        return $this->trace(__FUNCTION__, function () use ($schedules) {
            return DB::transaction(function () use ($schedules) {
                $ids = [];

                foreach ($schedules as $scheduleData) {
                    $schedule = ClassSchedule::create([
                        'class_id' => $scheduleData['class_id'],
                        'subject_id' => $scheduleData['subject_id'],
                        'teacher_id' => $scheduleData['teacher_id'],
                        'shift_id' => $scheduleData['shift_id'],
                        'day_of_week' => $scheduleData['day_of_week'],
                        'room' => $scheduleData['room'] ?? null,
                        'academic_year' => $scheduleData['academic_year'],
                        'year_level' => $scheduleData['year_level'],
                        'semester' => $scheduleData['semester'],
                    ]);

                    $ids[] = $schedule->id;
                }

                return ClassSchedule::with(['classroom', 'subject', 'teacher', 'shift'])
                    ->whereIn('id', $ids)
                    ->get();
            });
        });
    }

    private function busyKey(int $teacherId, array $slot): string
    {
        return "{$teacherId}:{$slot['day_of_week']}:{$slot['shift_id']}";
    }
}
