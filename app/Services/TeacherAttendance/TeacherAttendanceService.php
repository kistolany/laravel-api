<?php

namespace App\Services\TeacherAttendance;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherAttendanceService extends BaseService
{
    public function index(array $filters, ?Authenticatable $user): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters, $user): array {
            $date = $filters['date'] ?? now()->toDateString();
            $teacherModels = $this->teacherQuery($this->resolveTeacherScopeId($user))
                ->select('id', 'teacher_id', 'first_name', 'last_name', 'gender', 'position', 'major_id', 'subject_id', 'image')
                ->with(['major:id,name', 'subject:id,name'])
                ->get();

            $attendanceRecords = TeacherAttendance::query()
                ->select('id', 'teacher_id', 'session', 'status', 'check_in_time', 'check_out_time', 'note', 'replace_teacher_id', 'replace_status', 'replace_subject_id')
                ->with(['replaceTeacher:id,first_name,last_name', 'replaceSubject:id,name'])
                ->where('attendance_date', $date)
                ->whereIn('teacher_id', $teacherModels->pluck('id'))
                ->get()
                ->groupBy('teacher_id');

            $yearLevels = $this->teacherYearLevels($teacherModels->pluck('id')->all());

            return [
                'date' => $date,
                'teachers' => $teacherModels
                    ->map(fn (Teacher $teacher): array => $this->teacherAttendanceRow($teacher, $attendanceRecords->get($teacher->id, collect()), $yearLevels[$teacher->id] ?? []))
                    ->values(),
                'summary' => $this->buildSummary($date, $attendanceRecords->flatten()->values(), $teacherModels->count()),
            ];
        });
    }

    public function bulk(array $data, ?Authenticatable $user, ?int $recordedBy): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $user, $recordedBy): array {
            $this->assertCanManageRecords($data['records'], $this->resolveTeacherScopeId($user));

            $now = now();
            $rows = collect($data['records'])
                ->map(fn (array $record): array => [
                    'teacher_id'         => (int) $record['teacher_id'],
                    'attendance_date'    => $data['date'],
                    'session'            => (int) $record['session'],
                    'status'             => $record['status'],
                    'check_in_time'      => $record['check_in_time'] ?? null,
                    'check_out_time'     => $record['check_out_time'] ?? null,
                    'note'               => $record['note'] ?? null,
                    'recorded_by'        => $recordedBy,
                    'replace_teacher_id' => isset($record['replace_teacher_id']) ? (int) $record['replace_teacher_id'] : null,
                    'replace_status'     => $record['replace_status'] ?? null,
                    'replace_subject_id' => isset($record['replace_subject_id']) ? (int) $record['replace_subject_id'] : null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ])
                ->all();

            TeacherAttendance::upsert(
                $rows,
                ['teacher_id', 'attendance_date', 'session'],
                ['status', 'check_in_time', 'check_out_time', 'note', 'recorded_by', 'replace_teacher_id', 'replace_status', 'replace_subject_id', 'updated_at']
            );

            return [
                'date' => $data['date'],
                'saved' => count($rows),
                'summary' => $this->buildSummary($data['date']),
            ];
        });
    }

    public function weekly(array $filters, ?Authenticatable $user): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters, $user): array {
            $teachers = $this->teacherQuery($this->resolveTeacherScopeId($user))
                ->select('id', 'teacher_id', 'first_name', 'last_name', 'position', 'major_id', 'image')
                ->with(['major:id,name'])
                ->get();

            $teacherIds = $teachers->pluck('id')->all();

            // All schedules for these teachers (include start_date, end_date, day_of_week)
            $schedules = \App\Models\ClassSchedule::query()
                ->whereIn('teacher_id', $teacherIds)
                ->select('id', 'teacher_id', 'class_id', 'class_program_id', 'subject_id', 'shift_id', 'year_level', 'semester', 'start_date', 'end_date', 'day_of_week')
                ->with([
                    'classroom:id,name',
                    'classProgram:id,major_id,shift_id,year_level,semester',
                    'subject:id,name',
                    'shift:id,name,time_range',
                ])
                ->orderBy('subject_id')
                ->get()
                ->groupBy('teacher_id');

            $replacementOptions = $this->replacementOptions($teachers, $schedules->flatten(1));

            // Attendance records within range
            $attendances = TeacherAttendance::query()
                ->whereIn('teacher_id', $teacherIds)
                ->whereBetween('attendance_date', [$filters['from'], $filters['to']])
                ->select('teacher_id', 'schedule_id', 'session', 'attendance_date', 'status', 'note', 'replace_teacher_id', 'replace_status', 'replace_subject_id')
                ->with(['replaceTeacher:id,first_name,last_name', 'replaceSubject:id,name'])
                ->get()
                ->groupBy(fn ($a) => $a->schedule_id . '_' . (is_string($a->attendance_date) ? $a->attendance_date : $a->attendance_date->format('Y-m-d')));

            // All teachers list for substitute dropdown
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

            $result = $teachers->map(function (Teacher $teacher) use ($schedules, $attendances, $replacementOptions) {
                $teacherSchedules = $schedules->get($teacher->id, collect());

                $slots = $teacherSchedules->map(function (\App\Models\ClassSchedule $s) use ($attendances, $replacementOptions, $teacher) {
                    // Compute 15 week dates from start_date aligned to day_of_week
                    $weeks = $this->computeWeekDates($s->start_date, $s->day_of_week, 15);

                    $weekRows = collect($weeks)->map(function (string $date, int $wi) use ($s, $attendances) {
                        $key1 = $s->id . '_' . $date;
                        $bySession = collect($attendances->get($key1, collect()))->keyBy('session');
                        $s1 = $bySession->get(1);
                        $s2 = $bySession->get(2);

                        return [
                            'week'  => $wi + 1,
                            'date'  => $date,
                            's1'    => $this->weekSession($s1),
                            's2'    => $this->weekSession($s2),
                        ];
                    })->values();

                    return [
                        'schedule_id' => $s->id,
                        'subject'     => $s->subject?->name,
                        'subject_id'  => $s->subject_id,
                        'class'       => $s->classroom?->name,
                        'class_id'    => $s->class_id,
                        'class_program_id' => $s->class_program_id,
                        'major_id'    => $s->classProgram?->major_id ?? $teacher->major_id,
                        'year_level'  => $s->year_level ?? $s->classProgram?->year_level,
                        'semester'    => $s->semester ?? $s->classProgram?->semester,
                        'day_of_week' => $s->day_of_week,
                        'start_date'  => $s->start_date ? (is_string($s->start_date) ? $s->start_date : $s->start_date->format('Y-m-d')) : null,
                        'end_date'    => $s->end_date   ? (is_string($s->end_date)   ? $s->end_date   : $s->end_date->format('Y-m-d'))   : null,
                        'shift'       => $s->shift ? ['id' => $s->shift->id, 'name' => $s->shift->name, 'time_range' => $s->shift->time_range] : null,
                        'replacement_options' => $replacementOptions[$s->id] ?? [],
                        'weeks'       => $weekRows,
                    ];
                })->values();

                return [
                    'id'          => $teacher->id,
                    'teacher_id'  => $teacher->teacher_id,
                    'name'        => trim($teacher->first_name . ' ' . $teacher->last_name),
                    'position'    => $teacher->position,
                    'major'       => $teacher->major?->name,
                    'major_id'    => $teacher->major_id,
                    'image'       => $teacher->image,
                    'schedules'   => $slots,
                ];
            })->values();

            return [
                'from'         => $filters['from'],
                'to'           => $filters['to'],
                'teachers'     => $result,
                'all_teachers' => $allTeachers,
            ];
        });
    }

    private function computeWeekDates(?string $startDate, ?string $dayOfWeek, int $weeks): array
    {
        if (!$startDate || !$dayOfWeek) {
            // Fallback: use today as week 1 start, weekly for $weeks weeks
            $base = now()->startOfWeek();
        } else {
            $base = \Carbon\Carbon::parse($startDate);
        }

        $dates = [];
        $current = $base->copy();

        // Align to the correct day_of_week
        if ($dayOfWeek) {
            $map = ['Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,'Thursday'=>4,'Friday'=>5,'Saturday'=>6,'Sunday'=>0];
            $targetDow = $map[$dayOfWeek] ?? 1;
            // Find first occurrence of dayOfWeek on or after start_date
            while ($current->dayOfWeek !== $targetDow) {
                $current->addDay();
            }
        }

        for ($i = 0; $i < $weeks; $i++) {
            $dates[] = $current->format('Y-m-d');
            $current->addWeek();
        }

        return $dates;
    }

    private function weekSession(?TeacherAttendance $att): array
    {
        return [
            'id'                   => $att?->id,
            'status'               => $att?->status ?? null,
            'note'                 => $att?->note,
            'replace_teacher_id'   => $att?->replace_teacher_id,
            'replace_teacher_name' => $att?->replaceTeacher
                ? trim($att->replaceTeacher->first_name . ' ' . $att->replaceTeacher->last_name)
                : null,
            'replace_status'       => $att?->replace_status,
            'replace_subject_id'   => $att?->replace_subject_id,
            'replace_subject_name' => $att?->replaceSubject?->name,
            'is_saved'             => $att !== null,
        ];
    }

    private function replacementOptions(Collection $teachers, Collection $schedules): array
    {
        if ($schedules->isEmpty()) return [];

        $teacherIds = $teachers->pluck('id')->all();
        $busy = $schedules
            ->groupBy(fn (\App\Models\ClassSchedule $s) => $s->day_of_week . '_' . $s->shift_id . '_' . $s->teacher_id)
            ->map(fn () => true);

        $contextSchedules = \App\Models\ClassSchedule::query()
            ->whereIn('teacher_id', $teacherIds)
            ->select('id', 'teacher_id', 'class_program_id', 'subject_id', 'shift_id', 'year_level', 'semester', 'day_of_week')
            ->with(['subject:id,name', 'classProgram:id,major_id,shift_id,year_level,semester'])
            ->get()
            ->groupBy('teacher_id');

        $byTeacher = $teachers->keyBy('id');
        $options = [];

        foreach ($schedules as $slot) {
            $original = $byTeacher->get($slot->teacher_id);
            if (!$original) {
                $options[$slot->id] = [];
                continue;
            }

            $slotOptions = [];
            foreach ($teachers as $candidate) {
                if ($candidate->id === $slot->teacher_id) continue;
                if ($busy->has($slot->day_of_week . '_' . $slot->shift_id . '_' . $candidate->id)) continue;

                $slotContext = $this->replacementContext($slot, $original->major_id);
                $subjectSchedules = $contextSchedules->get($candidate->id, collect())
                    ->filter(fn (\App\Models\ClassSchedule $s) => $this->matchesReplacementContext($slotContext, $s, $candidate->major_id))
                    ->filter(fn (\App\Models\ClassSchedule $s) => $s->subject_id)
                    ->unique(fn (\App\Models\ClassSchedule $s) => $s->subject_id . '_' . $s->shift_id . '_' . ($s->year_level ?? $s->classProgram?->year_level))
                    ->sortBy('subject.name')
                    ->values();

                if ($subjectSchedules->isEmpty()) continue;

                $subjects = $subjectSchedules->map(fn (\App\Models\ClassSchedule $s) => [
                    'id' => $s->subject_id,
                    'name' => $s->subject?->name,
                    'shift_id' => $s->shift_id,
                    'year_level' => $s->year_level ?? $s->classProgram?->year_level,
                ])->values();

                $preferred = $subjectSchedules->first();

                $slotOptions[] = [
                    'id' => $candidate->id,
                    'name' => trim($candidate->first_name . ' ' . $candidate->last_name),
                    'major_id' => $candidate->major_id,
                    'subject_id' => $preferred->subject_id,
                    'subject_name' => $preferred->subject?->name,
                    'year_level' => $slotContext['year_level'],
                    'shift_id' => $slotContext['shift_id'],
                    'study_group' => $slotContext['study_group'],
                    'subjects' => $subjects,
                ];
            }

            $options[$slot->id] = collect($slotOptions)->sortBy('name')->values()->all();
        }

        return $options;
    }

    private function replacementContext(\App\Models\ClassSchedule $slot, ?int $fallbackMajorId = null): array
    {
        return [
            'major_id' => $slot->classProgram?->major_id ?? $fallbackMajorId,
            'year_level' => $slot->year_level ?? $slot->classProgram?->year_level,
            'shift_id' => $slot->shift_id ?? $slot->classProgram?->shift_id,
            'study_group' => $this->studyGroup($slot->day_of_week),
        ];
    }

    private function matchesReplacementContext(array $context, \App\Models\ClassSchedule $schedule, ?int $fallbackMajorId = null): bool
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

        $rows = \App\Models\ClassSchedule::query()
            ->whereIn('teacher_id', $teacherIds)
            ->whereNotNull('subject_id')
            ->select('teacher_id', 'subject_id', 'shift_id')
            ->with(['subject:id,name'])
            ->get()
            ->groupBy('teacher_id');

        $result = [];
        foreach ($rows as $teacherId => $teacherSchedules) {
            $result[$teacherId]['all'] = $teacherSchedules
                ->unique(fn (\App\Models\ClassSchedule $s) => $s->subject_id . '_' . $s->shift_id)
                ->map(fn (\App\Models\ClassSchedule $s) => [
                    'id' => $s->subject_id,
                    'name' => $s->subject?->name,
                    'shift_id' => $s->shift_id,
                ])
                ->values()
                ->all();
        }

        return $result;
    }

    public function history(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $rows = DB::table('teacher_attendances')
                ->whereBetween('attendance_date', [$filters['from'], $filters['to']])
                ->select(
                    'attendance_date as date',
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present"),
                    DB::raw("SUM(CASE WHEN status='Absent' THEN 1 ELSE 0 END) as absent"),
                    DB::raw("SUM(CASE WHEN status='Late' THEN 1 ELSE 0 END) as late"),
                    DB::raw("SUM(CASE WHEN status='Leave' THEN 1 ELSE 0 END) as `leave`")
                )
                ->groupBy('attendance_date')
                ->orderBy('attendance_date')
                ->get();

            return [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'history' => $rows,
            ];
        });
    }

    public function report(array $filters, ?Authenticatable $user): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters, $user): array {
            $teachers = $this->teacherQuery($this->resolveTeacherScopeId($user))
                ->select('id', 'teacher_id', 'first_name', 'last_name', 'position', 'major_id', 'image')
                ->with(['major:id,name'])
                ->get();

            $teacherIds = $teachers->pluck('id')->all();
            $with = [
                'schedule:id,class_id,subject_id,shift_id',
                'schedule.classroom:id,name',
                'schedule.subject:id,name',
                'schedule.shift:id,name,time_range',
                'teacher:id,first_name,last_name',
                'replaceTeacher:id,first_name,last_name',
                'replaceSubject:id,name',
            ];

            // Own attendance records (teacher_id = teacher)
            $ownRecords = TeacherAttendance::query()
                ->select('teacher_id', 'schedule_id', 'session', 'attendance_date', 'status', 'note', 'replace_teacher_id', 'replace_status', 'replace_subject_id')
                ->with($with)
                ->whereIn('teacher_id', $teacherIds)
                ->whereBetween('attendance_date', [$filters['from'], $filters['to']])
                ->orderBy('attendance_date')->orderBy('session')
                ->get()
                ->groupBy('teacher_id');

            // Substitute appearances (replace_teacher_id = teacher, meaning they covered for someone)
            $subRecords = TeacherAttendance::query()
                ->select('teacher_id', 'schedule_id', 'session', 'attendance_date', 'status', 'note', 'replace_teacher_id', 'replace_status', 'replace_subject_id')
                ->with($with)
                ->whereIn('replace_teacher_id', $teacherIds)
                ->whereBetween('attendance_date', [$filters['from'], $filters['to']])
                ->orderBy('attendance_date')
                ->get()
                ->groupBy('replace_teacher_id');

            $yearLevels = $this->teacherYearLevels($teacherIds);

            return [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'teachers' => $teachers
                    ->map(fn (Teacher $teacher): array => $this->teacherReportRow(
                        $teacher,
                        $ownRecords->get($teacher->id, collect()),
                        $subRecords->get($teacher->id, collect()),
                        $yearLevels[$teacher->id] ?? []
                    ))
                    ->values(),
            ];
        });
    }

    private function teacherQuery(?int $teacherId = null)
    {
        return Teacher::query()
            ->when($teacherId, fn ($query) => $query->where('id', $teacherId))
            ->orderBy('first_name');
    }

    private function resolveTeacherScopeId(?Authenticatable $user): ?int
    {
        if ($user instanceof Teacher) {
            return $user->id;
        }

        if ($user instanceof User && $user->teacher_id) {
            return (int) $user->teacher_id;
        }

        return null;
    }

    private function assertCanManageRecords(array $records, ?int $teacherScopeId): void
    {
        if (!$teacherScopeId) {
            return;
        }

        $unauthorized = collect($records)
            ->contains(fn (array $record): bool => (int) $record['teacher_id'] !== $teacherScopeId);

        if ($unauthorized) {
            throw new ApiException(ResponseStatus::FORBIDDEN, 'You can only manage your own attendance.');
        }
    }

    private function teacherAttendanceRow(Teacher $teacher, \Illuminate\Support\Collection $records, array $yearLevels = []): array
    {
        $bySession = $records->keyBy('session');

        return [
            'id'          => $teacher->id,
            'teacher_id'  => $teacher->teacher_id,
            'name'        => trim($teacher->first_name . ' ' . $teacher->last_name),
            'gender'      => $teacher->gender,
            'position'    => $teacher->position,
            'major'       => $teacher->major?->name,
            'subject'     => $teacher->subject?->name,
            'image'       => $teacher->image,
            'year_levels' => $yearLevels,
            'sessions'    => [
                1 => $this->sessionRecord($bySession->get(1)),
                2 => $this->sessionRecord($bySession->get(2)),
            ],
        ];
    }

    private function sessionRecord(?TeacherAttendance $record): array
    {
        return [
            'id'                   => $record?->id,
            'status'               => $record?->status ?? 'Present',
            'check_in_time'        => $record?->check_in_time,
            'check_out_time'       => $record?->check_out_time,
            'note'                 => $record?->note,
            'replace_teacher_id'   => $record?->replace_teacher_id,
            'replace_teacher_name' => $record?->replaceTeacher
                ? trim($record->replaceTeacher->first_name . ' ' . $record->replaceTeacher->last_name)
                : null,
            'replace_status'       => $record?->replace_status,
            'replace_subject_id'   => $record?->replace_subject_id,
            'replace_subject_name' => $record?->replaceSubject?->name,
        ];
    }

    private function teacherReportRow(Teacher $teacher, Collection $records, Collection $subRecords, array $yearLevels = []): array
    {
        // Group own records by schedule_id so each subject/class gets its own counts
        $bySchedule = $records->groupBy('schedule_id');

        $schedules = $bySchedule->map(function (Collection $schedRecs, $scheduleId): array {
            $firstRec = $schedRecs->first();
            $s        = $firstRec?->schedule;

            $present = $schedRecs->where('status', 'Present')->count();
            $absent  = $schedRecs->where('status', 'Absent')->count();
            $total   = $schedRecs->count();

            // date → { session → record }
            $sessions = $schedRecs->groupBy(fn (TeacherAttendance $r) => is_string($r->attendance_date)
                    ? $r->attendance_date
                    : $r->attendance_date->format('Y-m-d')
            )->map(fn (Collection $dayRecs) => $dayRecs->keyBy('session')->map(fn (TeacherAttendance $r) => [
                'status'          => $r->status,
                'note'            => $r->note,
                'replace_teacher' => $r->replaceTeacher
                    ? trim($r->replaceTeacher->first_name . ' ' . $r->replaceTeacher->last_name)
                    : null,
                'replace_status'  => $r->replace_status,
            ]));

            return [
                'schedule_id' => $scheduleId,
                'subject'     => $s?->subject?->name,
                'class'       => $s?->classroom?->name,
                'shift'       => $s?->shift ? ['name' => $s->shift->name, 'time_range' => $s->shift->time_range] : null,
                'present'     => $present,
                'absent'      => $absent,
                'total'       => $total,
                'rate'        => $total > 0 ? round(($present / $total) * 100) : 0,
                // keyed by date → { session_number → { status, note, ... } }
                'sessions'    => $sessions,
            ];
        })->values();

        // Substitute rows grouped by the schedule the teacher covered
        $subSchedules = $subRecords->groupBy('schedule_id')->map(function (Collection $subRecs, $scheduleId): array {
            $firstRec = $subRecs->first();
            $s        = $firstRec?->schedule;

            $sessions = $subRecs->groupBy(fn (TeacherAttendance $r) => is_string($r->attendance_date)
                    ? $r->attendance_date
                    : $r->attendance_date->format('Y-m-d')
            )->map(fn (Collection $dayRecs) => $dayRecs->keyBy('session')->map(fn (TeacherAttendance $r) => [
                'status'           => $r->replace_status ?? 'Present',
                'absent_teacher'   => $r->teacher
                    ? trim($r->teacher->first_name . ' ' . $r->teacher->last_name)
                    : null,
                'subject_taught'   => $r->replaceSubject?->name,
                'scheduled_subject'=> $s?->subject?->name,
            ]));

            return [
                'schedule_id'       => $scheduleId,
                'subject'           => $subRecs->first()?->replaceSubject?->name, // what was actually taught
                'scheduled_subject' => $s?->subject?->name,
                'class'    => $s?->classroom?->name,
                'shift'    => $s?->shift ? ['name' => $s->shift->name, 'time_range' => $s->shift->time_range] : null,
                'sessions' => $sessions,
            ];
        })->values();

        return [
            'id'          => $teacher->id,
            'teacher_id'  => $teacher->teacher_id,
            'name'        => trim($teacher->first_name . ' ' . $teacher->last_name),
            'position'    => $teacher->position,
            'major'       => $teacher->major?->name,
            'image'       => $teacher->image,
            'year_levels' => $yearLevels,
            // per-schedule rows — each has its own P/A counts
            'schedules'      => $schedules,
            'sub_schedules'  => $subSchedules,
        ];
    }

    private function teacherYearLevels(array $teacherIds): array
    {
        return [];
    }

    private function collectDates(Collection $records, string $status): Collection
    {
        return $records
            ->where('status', $status)
            ->pluck('attendance_date')
            ->map(fn ($date) => is_string($date) ? $date : $date->format('Y-m-d'))
            ->values();
    }

    private function buildSummary(string $date, ?Collection $records = null, ?int $total = null): array
    {
        $total ??= Teacher::count();

        if ($records === null) {
            $statusCounts = TeacherAttendance::query()
                ->where('attendance_date', $date)
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status');

            $marked = (int) $statusCounts->sum();

            return [
                'total' => $total,
                'marked' => $marked,
                'unmarked' => max(0, $total - $marked),
                'present' => (int) ($statusCounts->get('Present') ?? 0),
                'absent' => (int) ($statusCounts->get('Absent') ?? 0),
                'late' => (int) ($statusCounts->get('Late') ?? 0),
                'leave' => (int) ($statusCounts->get('Leave') ?? 0),
            ];
        }

        return [
            'total' => $total,
            'marked' => $records->count(),
            'unmarked' => max(0, $total - $records->count()),
            'present' => $records->where('status', 'Present')->count(),
            'absent' => $records->where('status', 'Absent')->count(),
            'late' => $records->where('status', 'Late')->count(),
            'leave' => $records->where('status', 'Leave')->count(),
        ];
    }
}
