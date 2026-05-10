<?php

namespace App\Http\Controllers\ApiController\TeacherAttendance;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Models\Teacher;
use App\Services\TeacherAttendance\TeacherDailyScheduleService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TeacherDailyScheduleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly TeacherDailyScheduleService $service) {}

    public function daily(Request $request): JsonResponse
    {
        $request->validate(['date' => 'nullable|date']);
        $data = $this->service->daily($request->only('date'), Auth::user());
        return $this->success($data);
    }

    public function bulkBySchedule(Request $request): JsonResponse
    {
        $request->validate([
            'date'                           => 'required|date',
            'records'                        => 'required|array|min:1',
            'records.*.teacher_id'           => 'required|exists:teachers,id',
            'records.*.schedule_id'          => 'nullable|exists:class_schedules,id',
            'records.*.season'               => 'required|integer|in:1,2',
            'records.*.status'               => 'required|in:Present,Absent',
            'records.*.note'                 => 'nullable|string|max:255',
            'records.*.replace_teacher_id'   => 'nullable|exists:teachers,id',
            'records.*.replace_status'       => 'nullable|in:Present,Absent',
            'records.*.replace_subject_id'   => 'nullable|exists:subjects,id',
        ]);

        $payload = $request->all();

        // Enforce replacement rule: same major, same year, same shift, free on the
        // original day/shift, and the saved subject must be the substitute teacher's own subject.
        foreach ($payload['records'] ?? [] as $i => $record) {
            if (empty($record['replace_teacher_id']) || empty($record['schedule_id'])) {
                continue;
            }

            $schedule        = ClassSchedule::find($record['schedule_id']);
            $originalTeacher = $schedule ? Teacher::find($schedule->teacher_id) : null;
            $substitute      = Teacher::find($record['replace_teacher_id']);

            if ($originalTeacher && $substitute && $originalTeacher->major_id !== $substitute->major_id) {
                throw ValidationException::withMessages([
                    "records.{$i}.replace_teacher_id" => "Substitute teacher must belong to the same major as the original teacher.",
                ]);
            }

            $busy = ClassSchedule::query()
                ->where('teacher_id', $substitute?->id)
                ->where('day_of_week', $schedule?->day_of_week)
                ->where('shift_id', $schedule?->shift_id)
                ->exists();

            if ($busy) {
                throw ValidationException::withMessages([
                    "records.{$i}.replace_teacher_id" => "Substitute teacher already has a class in this day and shift.",
                ]);
            }

            $validSubjectIds = ClassSchedule::query()
                ->where('teacher_id', $substitute?->id)
                ->where('shift_id', $schedule?->shift_id)
                ->whereNotNull('subject_id')
                ->pluck('subject_id')
                ->unique()
                ->values();

            if ($validSubjectIds->isEmpty()) {
                throw ValidationException::withMessages([
                    "records.{$i}.replace_teacher_id" => "Substitute teacher must teach this same year level and shift.",
                ]);
            }

            if (!empty($record['replace_subject_id']) && !$validSubjectIds->contains((int) $record['replace_subject_id'])) {
                throw ValidationException::withMessages([
                    "records.{$i}.replace_subject_id" => "Replacement subject must be the substitute teacher's own subject for this year and shift.",
                ]);
            }

            if (empty($payload['records'][$i]['replace_subject_id'])) {
                $payload['records'][$i]['replace_subject_id'] = (int) $validSubjectIds->first();
            }
        }

        $data = $this->service->bulkBySchedule($payload, Auth::id());
        return $this->success($data);
    }
}
