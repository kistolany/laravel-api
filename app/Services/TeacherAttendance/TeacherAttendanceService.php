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
                ->select('id', 'teacher_id', 'status', 'check_in_time', 'check_out_time', 'note')
                ->where('attendance_date', $date)
                ->whereIn('teacher_id', $teacherModels->pluck('id'))
                ->get()
                ->keyBy('teacher_id');

            return [
                'date' => $date,
                'teachers' => $teacherModels
                    ->map(fn (Teacher $teacher): array => $this->teacherAttendanceRow($teacher, $attendanceRecords->get($teacher->id)))
                    ->values(),
                'summary' => $this->buildSummary($date, $attendanceRecords->values(), $teacherModels->count()),
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
                    'teacher_id' => (int) $record['teacher_id'],
                    'attendance_date' => $data['date'],
                    'status' => $record['status'],
                    'check_in_time' => $record['check_in_time'] ?? null,
                    'check_out_time' => $record['check_out_time'] ?? null,
                    'note' => $record['note'] ?? null,
                    'recorded_by' => $recordedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            TeacherAttendance::upsert(
                $rows,
                ['teacher_id', 'attendance_date'],
                ['status', 'check_in_time', 'check_out_time', 'note', 'recorded_by', 'updated_at']
            );

            return [
                'date' => $data['date'],
                'saved' => count($rows),
                'summary' => $this->buildSummary($data['date']),
            ];
        });
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
                ->select('id', 'first_name', 'last_name', 'position', 'major_id', 'image')
                ->with(['major:id,name'])
                ->get();

            $records = TeacherAttendance::query()
                ->select('teacher_id', 'attendance_date', 'status')
                ->whereBetween('attendance_date', [$filters['from'], $filters['to']])
                ->orderBy('attendance_date')
                ->get()
                ->groupBy('teacher_id');

            return [
                'from' => $filters['from'],
                'to' => $filters['to'],
                'teachers' => $teachers
                    ->map(fn (Teacher $teacher): array => $this->teacherReportRow($teacher, $records->get($teacher->id, collect())))
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

    private function teacherAttendanceRow(Teacher $teacher, ?TeacherAttendance $record): array
    {
        return [
            'id' => $teacher->id,
            'teacher_id' => $teacher->teacher_id,
            'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
            'gender' => $teacher->gender,
            'position' => $teacher->position,
            'major' => $teacher->major?->name,
            'subject' => $teacher->subject?->name,
            'image' => $teacher->image,
            'attendance' => $record ? [
                'id' => $record->id,
                'status' => $record->status,
                'check_in_time' => $record->check_in_time,
                'check_out_time' => $record->check_out_time,
                'note' => $record->note,
            ] : null,
        ];
    }

    private function teacherReportRow(Teacher $teacher, Collection $records): array
    {
        $present = $records->where('status', 'Present')->count();
        $absent = $records->where('status', 'Absent')->count();
        $late = $records->where('status', 'Late')->count();
        $leave = $records->where('status', 'Leave')->count();
        $total = $records->count();

        return [
            'id' => $teacher->id,
            'name' => trim($teacher->first_name . ' ' . $teacher->last_name),
            'position' => $teacher->position,
            'major' => $teacher->major?->name,
            'image' => $teacher->image,
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'rate' => $total > 0 ? round(($present / $total) * 100) : 0,
            'absent_dates' => $this->collectDates($records, 'Absent'),
            'leave_dates' => $this->collectDates($records, 'Leave'),
            'late_dates' => $this->collectDates($records, 'Late'),
        ];
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
