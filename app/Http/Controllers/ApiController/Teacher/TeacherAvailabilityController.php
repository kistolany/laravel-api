<?php

namespace App\Http\Controllers\ApiController\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherAvailability;
use App\Models\Teacher;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAvailabilityController extends Controller
{
    use ApiResponseTrait;

    // GET /teacher-availability
    // Admin: all teachers' availability. Teacher: only their own.
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TeacherAvailability::with(['teacher', 'subject', 'shift']);

        // If authenticated as a teacher (via auth.teacher middleware), scope to self
        if ($request->attributes->get('auth_guard') === 'teacher') {
            $query->where('teacher_id', $user->id);
        }

        $rows = $query->get()->map(fn($a) => $this->format($a));

        return $this->success($rows->values()->all(), 'Availabilities retrieved.');
    }

    // GET /teacher-availability/by-teacher/{teacherId}
    // Admin use: get one teacher's availability
    public function byTeacher(int $teacherId): JsonResponse
    {
        $rows = TeacherAvailability::with(['subject', 'shift'])
            ->where('teacher_id', $teacherId)
            ->get()
            ->map(fn($a) => $this->format($a));

        return $this->success($rows->values()->all(), 'Availability retrieved.');
    }

    // GET /teacher-availability/all-summary
    // Admin: returns all teachers with their availabilities grouped
    public function allSummary(): JsonResponse
    {
        $teachers = Teacher::with(['subject'])
            ->orderBy('first_name')
            ->get();

        $availabilities = TeacherAvailability::with(['subject', 'shift'])
            ->get()
            ->groupBy('teacher_id');

        $result = $teachers->map(function ($t) use ($availabilities) {
            $avails = $availabilities->get($t->id, collect());
            return [
                'teacher_id'   => $t->id,
                'teacher_name' => trim($t->first_name . ' ' . $t->last_name),
                'subject'      => $t->subject ? ['id' => $t->subject->id, 'name' => $t->subject->name] : null,
                'availability' => $avails->map(fn($a) => $this->format($a))->values()->all(),
            ];
        });

        return $this->success($result->values()->all(), 'Summary retrieved.');
    }

    // POST /teacher-availability/sync
    // Teacher submits their full availability (replaces all existing rows for that teacher)
    // Body: { teacher_id, slots: [{ subject_id, shift_id, day_of_week }] }
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id'              => 'required|integer|exists:teachers,id',
            'slots'                   => 'required|array',
            'slots.*.subject_id'      => 'required|integer|exists:subjects,id',
            'slots.*.shift_id'        => 'required|integer|exists:shifts,id',
            'slots.*.day_of_week'     => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
        ]);

        DB::transaction(function () use ($data) {
            // Delete all existing availability for this teacher
            TeacherAvailability::where('teacher_id', $data['teacher_id'])->delete();

            // Re-insert
            $now = now();
            $rows = collect($data['slots'])->map(fn($s) => [
                'teacher_id'  => $data['teacher_id'],
                'subject_id'  => $s['subject_id'],
                'shift_id'    => $s['shift_id'],
                'day_of_week' => $s['day_of_week'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ])->unique(fn($r) => $r['subject_id'].'-'.$r['shift_id'].'-'.$r['day_of_week'])->values()->all();

            if (count($rows)) {
                TeacherAvailability::insert($rows);
            }
        });

        $saved = TeacherAvailability::with(['subject', 'shift'])
            ->where('teacher_id', $data['teacher_id'])
            ->get()
            ->map(fn($a) => $this->format($a));

        return $this->success($saved->values()->all(), 'Availability saved successfully.');
    }

    private function format(TeacherAvailability $a): array
    {
        return [
            'id'          => $a->id,
            'teacher_id'  => $a->teacher_id,
            'teacher_name'=> $a->teacher ? trim($a->teacher->first_name . ' ' . $a->teacher->last_name) : null,
            'subject_id'  => $a->subject_id,
            'subject_name'=> $a->subject?->name,
            'shift_id'    => $a->shift_id,
            'shift_name'  => $a->shift?->name,
            'shift_time'  => $a->shift?->time_range,
            'day_of_week' => $a->day_of_week,
        ];
    }
}
