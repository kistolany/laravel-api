<?php

namespace App\Services\TeacherAttendance;

use App\Models\ClassSchedule;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\BaseService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherDailyScheduleService extends BaseService
{
    /**
     * Return all teachers with their scheduled classes for a given date.
     * Each schedule slot has TWO season rows (Season 1 and Season 2),
     * each with its own attendance record.
     */
    public function daily(array $filters, ?Authenticatable $user): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters, $user): array {
            $date    = $filters['date'] ?? now()->toDateString();
            $dayName = now()->parse($date)->format('l');

            $teacherScopeId = $this->resolveTeacherScopeId($user);

            // 1. Teachers
            $teachers = Teacher::query()
                ->when($teacherScopeId, fn ($q) => $q->where('id', $teacherScopeId))
                ->select('id', 'teacher_id', 'first_name', 'last_name', 'position', 'major_id', 'image')
                ->with(['major:id,name'])
                ->orderBy('first_name')
                ->get();

            if ($teachers->isEmpty()) {
                return ['date' => $date, 'day' => $dayName, 'teachers' => [], 'summary' => $this->emptySummary()];
            }

            $teacherIds = $teachers->pluck('id')->all();

            // 2. Schedules for this day
            $schedules = ClassSchedule::query()
                ->whereIn('teacher_id', $teacherIds)
                ->where('day_of_week', $dayName)
                ->select('id', 'teacher_id', 'class_id', 'class_program_id', 'subject_id', 'shift_id', 'room_id', 'year_level', 'semester', 'start_date', 'day_of_week')
                ->with([
                    'classroom:id,name',
                    'classProgram:id,major_id,shift_id,year_level,semester',
                    'subject:id,name',
                    'shift:id,name,time_range',
                    'teacher:id,major_id',
                    'roomModel:id,name',
                ])
                ->orderBy('shift_id')
                ->get()
                ->groupBy('teacher_id');

            $contextSchedules = ClassSchedule::query()
                ->whereIn('teacher_id', $teacherIds)
                ->select('id', 'teacher_id', 'class_program_id', 'subject_id', 'shift_id', 'year_level', 'semester', 'day_of_week')
                ->with(['subject:id,name', 'classProgram:id,major_id,shift_id,year_level,semester'])
                ->get();

            $replacementOptions = $this->replacementOptions($teachers, $schedules->flatten(1), $contextSchedules);

            // 3. Existing attendance records keyed by (schedule_id, session)
            $attendances = TeacherAttendance::query()
                ->whereIn('teacher_id', $teacherIds)
                ->where('attendance_date', $date)
                ->select('id', 'teacher_id', 'schedule_id', 'session', 'status', 'note', 'replace_teacher_id', 'replace_status', 'replace_subject_id', 'replace_shift_id')
                ->with(['replaceTeacher:id,first_name,last_name', 'replaceSubject:id,name', 'replaceShift:id,name'])
                ->get()
                ->groupBy(fn ($a) => $a->schedule_id . '_' . $a->session);

            // 4. All teachers for same-major substitute dropdown
            $subjectsByTeacherYear = $this->teacherSubjectsByYear($teacherIds);
            $allTeachers = Teacher::query()
                ->select('id', 'teacher_id', 'first_name', 'last_name', 'major_id')
                ->orderBy('first_name')
                ->get()
                ->map(fn ($t) => [
                    'id'       => $t->id,
                    'name'     => trim($t->first_name . ' ' . $t->last_name),
                    'major_id' => $t->major_id,
                    'subjects_by_year' => $subjectsByTeacherYear[$t->id] ?? [],
                ]);

            // 5. Compose response — each schedule gets 2 season rows
            $rows = $teachers->map(function (Teacher $teacher) use ($schedules, $attendances, $replacementOptions) {
                $teacherSchedules = $schedules->get($teacher->id, collect());

                $slots = $teacherSchedules->map(function (ClassSchedule $s) use ($attendances, $teacher, $replacementOptions) {
                    $att1 = $attendances->get($s->id . '_1')?->first();
                    $att2 = $attendances->get($s->id . '_2')?->first();

                    return [
                        'schedule_id'        => $s->id,
                        'teacher_major_id'   => $teacher->major_id,
                        'schedule_subject_id'=> $s->subject_id,
                        'class'              => $s->classroom ? ['id' => $s->classroom->id, 'name' => $s->classroom->name] : null,
                        'class_program_id'   => $s->class_program_id,
                        'major_id'           => $s->classProgram?->major_id ?? $teacher->major_id,
                        'year_level'         => $s->year_level ?? $s->classProgram?->year_level,
                        'semester'           => $s->semester ?? $s->classProgram?->semester,
                        'subject'            => $s->subject   ? ['id' => $s->subject->id,   'name' => $s->subject->name]   : null,
                        'shift'              => $s->shift     ? ['id' => $s->shift->id, 'name' => $s->shift->name, 'time_range' => $s->shift->time_range] : null,
                        'room_id'          => $s->room_id,
                        'room_name'        => $s->roomModel?->name,
                        'start_date'       => $s->start_date ? (is_string($s->start_date) ? $s->start_date : $s->start_date->format('Y-m-d')) : null,
                        'day_of_week'      => $s->day_of_week,
                        'replacement_options' => $replacementOptions[$s->id] ?? [],
                        'seasons'          => [
                            1 => $this->seasonRow(1, $att1),
                            2 => $this->seasonRow(2, $att2),
                        ],
                    ];
                })->values();

                return [
                    'id'           => $teacher->id,
                    'teacher_id'   => $teacher->teacher_id,
                    'name'         => trim($teacher->first_name . ' ' . $teacher->last_name),
                    'position'     => $teacher->position,
                    'major'        => $teacher->major?->name,
                    'major_id'     => $teacher->major_id,
                    'image'        => $teacher->image,
                    'schedules'    => $slots,
                    'has_schedule' => $slots->isNotEmpty(),
                ];
            });

            // Summary counts by season
            $totalSeasons = 0;
            $presentCount = 0;
            $absentCount  = 0;
            foreach ($rows as $t) {
                foreach ($t['schedules'] as $s) {
                    foreach ($s['seasons'] as $season) {
                        $totalSeasons++;
                        if ($season['status'] === 'Present') $presentCount++;
                        elseif ($season['status'] === 'Absent') $absentCount++;
                    }
                }
            }

            return [
                'date'         => $date,
                'day'          => $dayName,
                'teachers'     => $rows->values(),
                'all_teachers' => $allTeachers,
                'summary'      => [
                    'total_teachers' => $teachers->count(),
                    'total_seasons'  => $totalSeasons,
                    'present'        => $presentCount,
                    'absent'         => $absentCount,
                    'unmarked'       => $totalSeasons - $presentCount - $absentCount,
                ],
            ];
        });
    }

    /**
     * Bulk upsert season attendance records.
     * Each record must have: teacher_id, schedule_id, season (1|2), status.
     */
    public function bulkBySchedule(array $data, ?int $recordedBy): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $recordedBy): array {
            $now  = now();
            $date = $data['date'];

            $rows = collect($data['records'])->map(fn (array $r) => [
                'teacher_id'         => (int) $r['teacher_id'],
                'schedule_id'        => isset($r['schedule_id']) ? (int) $r['schedule_id'] : null,
                'attendance_date'    => $date,
                'session'            => (int) ($r['season'] ?? 1),
                'status'             => $r['status'],
                'note'               => $r['note'] ?? null,
                'recorded_by'        => $recordedBy,
                'replace_teacher_id' => isset($r['replace_teacher_id']) ? (int) $r['replace_teacher_id'] : null,
                'replace_status'     => $r['replace_status'] ?? null,
                'replace_subject_id' => isset($r['replace_subject_id']) ? (int) $r['replace_subject_id'] : null,
                // replace_shift_id: set when substitute comes from a different shift
                'replace_shift_id'   => isset($r['replace_shift_id']) ? (int) $r['replace_shift_id'] : null,
                'created_at'         => $now,
                'updated_at'         => $now,
            ])->all();

            // Upsert keyed by (schedule_id, attendance_date, session)
            TeacherAttendance::upsert(
                $rows,
                ['schedule_id', 'attendance_date', 'session'],
                ['teacher_id', 'status', 'note', 'recorded_by', 'replace_teacher_id', 'replace_status', 'replace_subject_id', 'replace_shift_id', 'updated_at']
            );

            $summary = DB::table('teacher_attendances')
                ->where('attendance_date', $date)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            return [
                'date'  => $date,
                'saved' => count($rows),
                'summary' => [
                    'present' => (int) ($summary->get('Present') ?? 0),
                    'absent'  => (int) ($summary->get('Absent')  ?? 0),
                ],
            ];
        });
    }

    private function seasonRow(int $season, ?TeacherAttendance $att): array
    {
        return [
            'season'               => $season,
            'id'                   => $att?->id,
            'status'               => $att?->status ?? 'Present',
            'note'                 => $att?->note,
            'replace_teacher_id'   => $att?->replace_teacher_id,
            'replace_teacher_name' => $att?->replaceTeacher
                ? trim($att->replaceTeacher->first_name . ' ' . $att->replaceTeacher->last_name)
                : null,
            'replace_status'       => $att?->replace_status ?? 'Present',
            'replace_subject_id'   => $att?->replace_subject_id,
            'replace_subject_name' => $att?->replaceSubject?->name,
            // Cross-shift substitute: which shift the substitute actually belongs to
            'replace_shift_id'     => $att?->replace_shift_id,
            'replace_shift_name'   => $att?->replaceShift?->name,
            'is_saved'             => $att !== null,
        ];
    }

    private function replacementOptions(Collection $teachers, Collection $daySchedules, Collection $contextSchedules): array
    {
        if ($daySchedules->isEmpty()) return [];

        $teacherIds = $teachers->pluck('id')->all();

        // Track which teachers are already teaching on this day (by teacher_id → [shift_ids])
        // A teacher busy on shift X can still substitute for a class on shift Y (cross-shift).
        // A teacher is truly unavailable only if they teach the SAME shift on the same day.
        $busyOnShift = $daySchedules
            ->groupBy('teacher_id')
            ->map(fn (Collection $schedules) => $schedules->pluck('shift_id')->unique()->values()->all());

        $contextByTeacher = $contextSchedules->groupBy('teacher_id');

        $byTeacher = $teachers->keyBy('id');
        $options = [];

        foreach ($daySchedules as $slot) {
            $original = $byTeacher->get($slot->teacher_id);
            if (!$original) {
                $options[$slot->id] = [];
                continue;
            }

            $slotOptions = [];
            $slotContext = $this->replacementContext($slot, $original->major_id);

            foreach ($teachers as $candidate) {
                if ($candidate->id === $slot->teacher_id) continue;

                // Skip if already teaching the SAME shift on this day
                $candidateBusyShifts = $busyOnShift[$candidate->id] ?? [];
                if (in_array($slot->shift_id, $candidateBusyShifts, false)) continue;

                // Detect cross-shift: candidate teaches a different shift on this day
                $isCrossShift = !empty($candidateBusyShifts);
                $candidateShiftId = $isCrossShift ? $candidateBusyShifts[0] : null;

                // Match by major + year_level (cross-shift removes shift constraint)
                $subjectSchedules = $contextByTeacher->get($candidate->id, collect())
                    ->filter(fn (ClassSchedule $s) => $this->matchesReplacementContextCrossShift($slotContext, $s, $candidate->major_id, $isCrossShift))
                    ->filter(fn (ClassSchedule $s) => $s->subject_id)
                    ->unique(fn (ClassSchedule $s) => $s->subject_id . '_' . ($s->year_level ?? $s->classProgram?->year_level))
                    ->sortBy('subject.name')
                    ->values();

                if ($subjectSchedules->isEmpty()) continue;

                $subjects = $subjectSchedules->map(fn (ClassSchedule $s) => [
                    'id'         => $s->subject_id,
                    'name'       => $s->subject?->name,
                    'shift_id'   => $s->shift_id,
                    'year_level' => $s->year_level ?? $s->classProgram?->year_level,
                ])->values();

                $preferred = $subjectSchedules->first();

                $slotOptions[] = [
                    'id'            => $candidate->id,
                    'name'          => trim($candidate->first_name . ' ' . $candidate->last_name),
                    'major_id'      => $candidate->major_id,
                    'subject_id'    => $preferred->subject_id,
                    'subject_name'  => $preferred->subject?->name,
                    'year_level'    => $slotContext['year_level'],
                    'shift_id'      => $slotContext['shift_id'],
                    'study_group'   => $slotContext['study_group'],
                    'subjects'      => $subjects,
                    // Cross-shift info — frontend shows a badge when this is set
                    'cross_shift'        => $isCrossShift,
                    'replace_shift_id'   => $candidateShiftId,
                ];
            }

            $options[$slot->id] = collect($slotOptions)
                ->sortBy('cross_shift')   // same-shift first, cross-shift after
                ->sortBy('name')
                ->values()
                ->all();
        }

        return $options;
    }

    private function matchesReplacementContextCrossShift(array $context, ClassSchedule $schedule, ?int $fallbackMajorId, bool $isCrossShift): bool
    {
        $majorId   = $schedule->classProgram?->major_id ?? $fallbackMajorId;
        $yearLevel = $schedule->year_level ?? $schedule->classProgram?->year_level;
        $shiftId   = $schedule->shift_id   ?? $schedule->classProgram?->shift_id;

        $majorMatch     = (string) $majorId === (string) $context['major_id'];
        $yearMatch      = (string) $yearLevel === (string) $context['year_level'];
        $studyGrpMatch  = $this->studyGroup($schedule->day_of_week) === $context['study_group'];
        $shiftMatch     = (string) $shiftId === (string) $context['shift_id'];

        // Cross-shift: relax the shift constraint, keep major + year + study_group
        return $majorMatch && $yearMatch && $studyGrpMatch && ($isCrossShift || $shiftMatch);
    }

    private function replacementContext(ClassSchedule $slot, ?int $fallbackMajorId = null): array
    {
        return [
            'major_id' => $slot->classProgram?->major_id ?? $fallbackMajorId,
            'year_level' => $slot->year_level ?? $slot->classProgram?->year_level,
            'shift_id' => $slot->shift_id ?? $slot->classProgram?->shift_id,
            'study_group' => $this->studyGroup($slot->day_of_week),
        ];
    }

    private function matchesReplacementContext(array $context, ClassSchedule $schedule, ?int $fallbackMajorId = null): bool
    {
        $majorId = $schedule->classProgram?->major_id ?? $fallbackMajorId;
        $yearLevel = $schedule->year_level ?? $schedule->classProgram?->year_level;
        $shiftId = $schedule->shift_id ?? $schedule->classProgram?->shift_id;

        return (string) $majorId === (string) $context['major_id']
            && (string) $yearLevel === (string) $context['year_level']
            && (string) $shiftId === (string) $context['shift_id']
            && $this->studyGroup($schedule->day_of_week) === $context['study_group'];
    }

    private function studyGroup(?string $day): string
    {
        return in_array($day, ['Saturday', 'Sunday'], true) ? 'weekend' : 'weekday';
    }

    private function teacherSubjectsByYear(array $teacherIds): array
    {
        if (empty($teacherIds)) return [];

        $rows = ClassSchedule::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereNotNull('subject_id')
            ->select('teacher_id', 'subject_id', 'shift_id')
            ->with(['subject:id,name'])
            ->get()
            ->groupBy('teacher_id');

        $result = [];
        foreach ($rows as $teacherId => $teacherSchedules) {
            $result[$teacherId]['all'] = $teacherSchedules
                ->unique(fn (ClassSchedule $s) => $s->subject_id . '_' . $s->shift_id)
                ->map(fn (ClassSchedule $s) => [
                    'id' => $s->subject_id,
                    'name' => $s->subject?->name,
                    'shift_id' => $s->shift_id,
                ])
                ->values()
                ->all();
        }

        return $result;
    }

    private function resolveTeacherScopeId(?Authenticatable $user): ?int
    {
        if ($user instanceof Teacher) return $user->id;
        if ($user instanceof \App\Models\User && $user->teacher_id) return (int) $user->teacher_id;
        return null;
    }

    private function emptySummary(): array
    {
        return ['total_teachers' => 0, 'total_seasons' => 0, 'present' => 0, 'absent' => 0, 'unmarked' => 0];
    }
}
