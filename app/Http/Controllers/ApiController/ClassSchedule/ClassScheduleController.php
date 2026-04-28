<?php

namespace App\Http\Controllers\ApiController\ClassSchedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassSchedule\ClassScheduleRequest;
use App\Http\Resources\ClassSchedule\ClassScheduleResource;
use App\Models\ClassSchedule;
use App\Models\TeacherAvailability;
use App\Services\ClassSchedule\ClassScheduleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    // ── Auto-generate ─────────────────────────────────────────────────────────
    // POST /class-schedules/auto-generate
    // Body: { slots: [{ class_id, subject_id, shift_id, day_of_week, room, academic_year, year_level, semester }] }
    // Returns: { scheduled: [...], conflicts: [...], unmatched: [...] }
    public function autoGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slots'                    => 'required|array|min:1',
            'slots.*.class_id'         => 'required|integer|exists:classes,id',
            'slots.*.subject_id'       => 'required|integer|exists:subjects,id',
            'slots.*.shift_id'         => 'required|integer|exists:shifts,id',
            'slots.*.day_of_week'      => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'slots.*.room'             => 'nullable|string|max:100',
            'slots.*.academic_year'    => 'required|string|max:20',
            'slots.*.year_level'       => 'required|integer|min:1|max:6',
            'slots.*.semester'         => 'required|integer|min:1|max:3',
        ]);

        // Load all availability in one query
        $allAvailability = TeacherAvailability::with(['teacher'])->get();

        // Track which teachers are already assigned per day+shift (to detect conflicts)
        $assigned = []; // "teacher_id:day:shift_id" => true

        $scheduled  = [];
        $conflicts  = [];
        $unmatched  = [];

        foreach ($data['slots'] as $slot) {
            // Find teachers available for this subject+shift+day
            $candidates = $allAvailability->filter(
                fn($a) =>
                    $a->subject_id  == $slot['subject_id']  &&
                    $a->shift_id    == $slot['shift_id']    &&
                    $a->day_of_week == $slot['day_of_week']
            );

            if ($candidates->isEmpty()) {
                $unmatched[] = $slot;
                continue;
            }

            // Pick first candidate not already assigned on this day+shift
            $chosenAvail = null;
            $isConflict  = false;

            foreach ($candidates as $avail) {
                $busyKey = "{$avail->teacher_id}:{$slot['day_of_week']}:{$slot['shift_id']}";
                if (!isset($assigned[$busyKey])) {
                    $chosenAvail = $avail;
                    break;
                }
            }

            // If all candidates are busy, take first and flag as conflict
            if (!$chosenAvail) {
                $chosenAvail = $candidates->first();
                $isConflict  = true;
            }

            $busyKey = "{$chosenAvail->teacher_id}:{$slot['day_of_week']}:{$slot['shift_id']}";
            $assigned[$busyKey] = true;

            $row = array_merge($slot, ['teacher_id' => $chosenAvail->teacher_id]);

            if ($isConflict) {
                $conflicts[] = array_merge($row, [
                    'teacher_name' => trim($chosenAvail->teacher->first_name . ' ' . $chosenAvail->teacher->last_name),
                    'conflict_reason' => 'Teacher already assigned on this day & shift',
                ]);
            } else {
                $scheduled[] = array_merge($row, [
                    'teacher_name' => trim($chosenAvail->teacher->first_name . ' ' . $chosenAvail->teacher->last_name),
                ]);
            }
        }

        return $this->success([
            'scheduled' => $scheduled,
            'conflicts' => $conflicts,
            'unmatched' => $unmatched,
        ], 'Auto-generate preview ready.');
    }

    // POST /class-schedules/auto-generate/confirm
    // Body: { schedules: [{ class_id, subject_id, teacher_id, shift_id, day_of_week, room, academic_year, year_level, semester }] }
    public function autoGenerateConfirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedules'                    => 'required|array|min:1',
            'schedules.*.class_id'         => 'required|integer|exists:classes,id',
            'schedules.*.subject_id'       => 'required|integer|exists:subjects,id',
            'schedules.*.teacher_id'       => 'required|integer|exists:teachers,id',
            'schedules.*.shift_id'         => 'required|integer|exists:shifts,id',
            'schedules.*.day_of_week'      => 'required|string',
            'schedules.*.room'             => 'nullable|string|max:100',
            'schedules.*.academic_year'    => 'required|string|max:20',
            'schedules.*.year_level'       => 'required|integer',
            'schedules.*.semester'         => 'required|integer',
        ]);

        $created = DB::transaction(function () use ($data) {
            $now = now();
            $ids = [];
            foreach ($data['schedules'] as $s) {
                $schedule = ClassSchedule::create([
                    'class_id'      => $s['class_id'],
                    'subject_id'    => $s['subject_id'],
                    'teacher_id'    => $s['teacher_id'],
                    'shift_id'      => $s['shift_id'],
                    'day_of_week'   => $s['day_of_week'],
                    'room'          => $s['room'] ?? null,
                    'academic_year' => $s['academic_year'],
                    'year_level'    => $s['year_level'],
                    'semester'      => $s['semester'],
                ]);
                $ids[] = $schedule->id;
            }
            return ClassSchedule::with(['classroom','subject','teacher','shift'])
                ->whereIn('id', $ids)->get();
        });

        return $this->success(
            ClassScheduleResource::collection($created),
            count($created) . ' schedules created successfully.'
        );
    }
}
