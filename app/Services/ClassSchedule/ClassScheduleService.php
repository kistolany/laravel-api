<?php

namespace App\Services\ClassSchedule;

use App\Services\BaseService;
use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\ClassSchedule\ClassScheduleResource;
use App\Models\ClassProgram;
use App\Models\ClassSchedule;
use App\Models\Shift;
use App\Models\TeacherAvailability;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ClassScheduleService extends BaseService
{
    private const WEEKEND_DAYS = ['Saturday', 'Sunday'];
    private const WEEKEND_SHIFT_RANGES = ['07:30-10:00', '11:00-14:30', '15:00-17:30'];

    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = ClassSchedule::with(['classProgram.major', 'classProgram.shift', 'classroom.programs.major', 'subject', 'teacher', 'shift', 'roomModel'])->latest();

            return $this->paginateResponse($query, ClassScheduleResource::class);
        });
    }

    public function findById(int $id): ClassSchedule
    {
        return $this->trace(__FUNCTION__, function () use ($id): ClassSchedule {
            $schedule = ClassSchedule::with(['classProgram.major', 'classProgram.shift', 'classroom.programs.major', 'subject', 'teacher', 'shift', 'roomModel'])->find($id);

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
            $payload = $this->withProgramContext($data);
            $this->assertDayShiftRules($payload);
            $this->assertScheduleConflicts($payload);

            $schedule = ClassSchedule::create($payload);

            return $schedule->load(['classProgram.major', 'classProgram.shift', 'classroom.programs.major', 'subject', 'teacher', 'shift', 'roomModel']);
        });
    }

    public function update(int $id, array $data): ClassSchedule
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): ClassSchedule {
            $schedule = $this->findById($id);

            $payload = $this->withProgramContext($data);
            $this->assertDayShiftRules($payload);
            $this->assertScheduleConflicts($payload, $id);

            $schedule->update($payload);

            return $schedule->load(['classProgram.major', 'classProgram.shift', 'classroom.programs.major', 'subject', 'teacher', 'shift', 'roomModel']);
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
            $query = ClassSchedule::with(['classProgram.major', 'classProgram.shift', 'classroom.programs.major', 'subject', 'teacher', 'shift', 'roomModel'])
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
                    $payload = $this->withProgramContext([
                        'class_program_id' => $scheduleData['class_program_id'] ?? null,
                        'class_id'   => $scheduleData['class_id'],
                        'subject_id' => $scheduleData['subject_id'],
                        'teacher_id' => $scheduleData['teacher_id'],
                        'shift_id'   => $scheduleData['shift_id'],
                        'day_of_week'=> $scheduleData['day_of_week'],
                        'room_id'    => $scheduleData['room_id'] ?? null,
                    ]);

                    $this->assertDayShiftRules($payload);
                    $this->assertScheduleConflicts($payload);

                    $schedule = ClassSchedule::create($payload);

                    $ids[] = $schedule->id;
                }

                return ClassSchedule::with(['classProgram.major', 'classProgram.shift', 'classroom.programs.major', 'subject', 'teacher', 'shift', 'roomModel'])
                    ->whereIn('id', $ids)
                    ->get();
            });
        });
    }

    private function busyKey(int $teacherId, array $slot): string
    {
        return "{$teacherId}:{$slot['day_of_week']}:{$slot['shift_id']}";
    }

    private function withProgramContext(array $data): array
    {
        $program = null;
        $isWeekendScheduleBlock = $this->isWeekendScheduleBlock($data);

        if (!empty($data['class_program_id'])) {
            $program = ClassProgram::find($data['class_program_id']);
        }

        if (!$program && !empty($data['class_id'])) {
            $program = ClassProgram::query()
                ->where('class_id', $data['class_id'])
                ->when(($data['shift_id'] ?? null) && !$isWeekendScheduleBlock, fn ($query, $value) => $query->where(fn ($q) => $q->whereNull('shift_id')->orWhere('shift_id', $data['shift_id'])))
                ->when($data['year_level'] ?? null, fn ($query, $value) => $query->where(fn ($q) => $q->whereNull('year_level')->orWhere('year_level', $value)))
                ->when($data['semester'] ?? null, fn ($query, $value) => $query->where(fn ($q) => $q->whereNull('semester')->orWhere('semester', $value)))
                ->when($data['academic_year'] ?? null, fn ($query, $value) => $query->where(fn ($q) => $q->whereNull('academic_year')->orWhere('academic_year', $value)))
                ->first();
        }

        if (!$program) {
            return $data;
        }

        $data['class_program_id'] = $program->id;
        $data['class_id'] = $program->class_id;
        $data['shift_id'] = $isWeekendScheduleBlock ? ($data['shift_id'] ?? $program->shift_id) : ($program->shift_id ?? ($data['shift_id'] ?? null));
        $data['year_level'] = $program->year_level ?? ($data['year_level'] ?? null);
        $data['semester'] = $program->semester ?? ($data['semester'] ?? null);
        $data['academic_year'] = $program->academic_year ?? ($data['academic_year'] ?? null);

        return $data;
    }

    private function isWeekendScheduleBlock(array $data): bool
    {
        if (!in_array($data['day_of_week'] ?? null, self::WEEKEND_DAYS, true) || empty($data['shift_id'])) {
            return false;
        }

        $shift = Shift::find($data['shift_id']);

        return $shift ? $this->isWeekendShift($shift) : false;
    }

    private function assertDayShiftRules(array $data): void
    {
        $day = $data['day_of_week'] ?? null;
        $shiftId = $data['shift_id'] ?? null;

        if (!$day || !$shiftId) {
            return;
        }

        $shift = Shift::find($shiftId);
        if (!$shift) {
            return;
        }

        $isWeekendDay = in_array($day, self::WEEKEND_DAYS, true);
        $isWeekendShift = $this->isWeekendShift($shift);

        if ($isWeekendDay && !$isWeekendShift) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'Weekend schedules must use one of these shifts: 07:30 - 10:00, 11:00 - 14:30, or 15:00 - 17:30.'
            );
        }

        if (!$isWeekendDay && $isWeekendShift) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'Weekend shifts can only be used on Saturday or Sunday.'
            );
        }
    }

    private function assertScheduleConflicts(array $data, ?int $ignoreId = null): void
    {
        if (empty($data['day_of_week']) || empty($data['shift_id'])) {
            return;
        }

        $this->assertClassProgramSlotAvailable($data, $ignoreId);
        $this->assertTeacherSlotAvailable($data, $ignoreId);
        $this->assertRoomSlotAvailable($data, $ignoreId);
    }

    private function assertClassProgramSlotAvailable(array $data, ?int $ignoreId = null): void
    {
        if (empty($data['class_program_id']) && empty($data['class_id'])) {
            return;
        }

        $query = $this->slotQuery($data, $ignoreId);

        $query->where(function ($scope) use ($data): void {
            if (!empty($data['class_program_id'])) {
                $scope->where('class_program_id', $data['class_program_id']);
            }

            if (!empty($data['class_id'])) {
                $method = !empty($data['class_program_id']) ? 'orWhere' : 'where';
                $scope->{$method}(function ($classScope) use ($data): void {
                    $classScope->where('class_id', $data['class_id']);
                    $this->whereContextOverlaps($classScope, 'academic_year', $data['academic_year'] ?? null);
                    $this->whereContextOverlaps($classScope, 'year_level', $data['year_level'] ?? null);
                    $this->whereContextOverlaps($classScope, 'semester', $data['semester'] ?? null);
                });
            }
        });

        $conflict = $query->first();

        if ($conflict) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                'This class program already has a schedule for this day and shift.',
                ['conflict' => $this->conflictData('class_program', $conflict)]
            );
        }
    }

    private function assertTeacherSlotAvailable(array $data, ?int $ignoreId = null): void
    {
        if (empty($data['teacher_id'])) {
            return;
        }

        $query = $this->slotQuery($data, $ignoreId)
            ->where('teacher_id', $data['teacher_id']);

        $this->whereContextOverlaps($query, 'academic_year', $data['academic_year'] ?? null);
        $this->whereContextOverlaps($query, 'semester', $data['semester'] ?? null);

        $conflict = $query->first();

        if ($conflict) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                'This teacher already has a schedule for this academic term, day, and shift.',
                ['conflict' => $this->conflictData('teacher', $conflict)]
            );
        }
    }

    private function assertRoomSlotAvailable(array $data, ?int $ignoreId = null): void
    {
        if (empty($data['room_id'])) {
            return;
        }

        $query = $this->slotQuery($data, $ignoreId)
            ->where('room_id', $data['room_id']);

        $this->whereContextOverlaps($query, 'academic_year', $data['academic_year'] ?? null);
        $this->whereContextOverlaps($query, 'semester', $data['semester'] ?? null);

        $conflict = $query->first();

        if ($conflict) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                'This room is already used for this academic term, day, and shift.',
                ['conflict' => $this->conflictData('room', $conflict)]
            );
        }
    }

    private function slotQuery(array $data, ?int $ignoreId = null)
    {
        $query = ClassSchedule::with(['classroom', 'subject', 'teacher', 'shift', 'roomModel'])
            ->where('day_of_week', $data['day_of_week'])
            ->where('shift_id', $data['shift_id']);

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query;
    }

    private function whereContextOverlaps($query, string $column, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $query->where(function ($scope) use ($column, $value): void {
            $scope->whereNull($column)->orWhere($column, $value);
        });
    }

    private function conflictData(string $type, ClassSchedule $schedule): array
    {
        return [
            'type' => $type,
            'schedule_id' => $schedule->id,
            'class_id' => $schedule->class_id,
            'class_name' => $schedule->classroom?->name,
            'subject_id' => $schedule->subject_id,
            'subject_name' => $schedule->subject?->name,
            'teacher_id' => $schedule->teacher_id,
            'teacher_name' => $schedule->teacher ? trim($schedule->teacher->first_name . ' ' . $schedule->teacher->last_name) : null,
            'room_id' => $schedule->room_id,
            'room_name' => $schedule->roomModel?->name,
            'day_of_week' => $schedule->day_of_week,
            'shift_id' => $schedule->shift_id,
            'shift_name' => $schedule->shift?->name,
            'academic_year' => $schedule->academic_year,
            'year_level' => $schedule->year_level,
            'semester' => $schedule->semester,
        ];
    }

    private function isWeekendShift(Shift $shift): bool
    {
        return in_array($this->normalizeTimeRange($shift->time_range), self::WEEKEND_SHIFT_RANGES, true);
    }

    private function normalizeTimeRange(?string $value): string
    {
        $value = strtolower((string) $value);
        $value = str_replace([' ', '–', '—'], ['', '-', '-'], $value);

        return $value;
    }
}
