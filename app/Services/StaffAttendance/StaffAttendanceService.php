<?php

namespace App\Services\StaffAttendance;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffAttendanceService extends BaseService
{
    public function index(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $date = $filters['date'] ?? now()->toDateString();
            $staffUsers = $this->staffUsersQuery()->get();

            if (!$this->attendanceTableExists()) {
                return [
                    'date' => $date,
                    'staff' => $staffUsers->map(fn (User $user) => $this->staffRow($user))->values(),
                    'summary' => $this->buildSummary($date, collect(), $staffUsers->count()),
                ];
            }

            $attendanceRecords = StaffAttendance::query()
                ->select('id', 'user_id', 'status', 'check_in_time', 'check_out_time', 'note')
                ->where('attendance_date', $date)
                ->get()
                ->keyBy('user_id');

            return [
                'date' => $date,
                'staff' => $staffUsers
                    ->map(fn (User $user) => $this->staffRow($user, $attendanceRecords->get($user->id)))
                    ->values(),
                'summary' => $this->buildSummary($date, $attendanceRecords->values(), $staffUsers->count()),
            ];
        });
    }

    public function bulk(array $data, ?int $recordedBy): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $recordedBy): array {
            if (!$this->attendanceTableExists()) {
                throw new ApiException(
                    ResponseStatus::INTERNAL_SERVER_ERROR,
                    'Staff attendance storage is not ready yet. Run the latest database migrations first.',
                    ['migration' => ['Staff attendance storage is not ready yet. Run the latest database migrations first.']]
                );
            }

            $this->assertStaffUsersAreEligible($data['records']);

            $now = now();
            $rows = collect($data['records'])
                ->map(fn (array $record): array => [
                    'user_id' => (int) $record['user_id'],
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

            StaffAttendance::upsert(
                $rows,
                ['user_id', 'attendance_date'],
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
            $from = $filters['from'];
            $to = $filters['to'];

            if (!$this->attendanceTableExists()) {
                return [
                    'from' => $from,
                    'to' => $to,
                    'history' => [],
                ];
            }

            $rows = DB::table('staff_attendances')
                ->whereBetween('attendance_date', [$from, $to])
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
                'from' => $from,
                'to' => $to,
                'history' => $rows,
            ];
        });
    }

    public function report(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $from = $filters['from'];
            $to = $filters['to'];
            $staffUsers = $this->staffUsersQuery()->get();

            if (!$this->attendanceTableExists()) {
                return [
                    'from' => $from,
                    'to' => $to,
                    'staff' => $staffUsers->map(fn (User $user) => $this->staffReportRow($user))->values(),
                ];
            }

            $records = StaffAttendance::query()
                ->select('user_id', 'attendance_date', 'status')
                ->whereBetween('attendance_date', [$from, $to])
                ->orderBy('attendance_date')
                ->get()
                ->groupBy('user_id');

            return [
                'from' => $from,
                'to' => $to,
                'staff' => $staffUsers
                    ->map(fn (User $user) => $this->staffReportRow($user, $records->get($user->id, collect())))
                    ->values(),
            ];
        });
    }

    private function assertStaffUsersAreEligible(array $records): void
    {
        $requestedIds = collect($records)
            ->pluck('user_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $staffIds = $this->staffUsersQuery()
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($value) => (int) $value);

        if ($staffIds->count() !== $requestedIds->count()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                'One or more selected users are not eligible for staff attendance.',
                ['records' => ['One or more selected users are not eligible for staff attendance.']]
            );
        }
    }

    private function staffRow(User $user, ?StaffAttendance $record = null): array
    {
        return [
            'id' => $user->id,
            'staff_id' => $user->staff_id,
            'name' => $user->full_name ?: $user->username ?: 'Unknown staff',
            'username' => $user->username,
            'role' => $user->role?->name,
            'department' => $user->department,
            'position' => $user->position,
            'phone' => $user->phone,
            'image' => $user->image,
            'attendance' => $record ? [
                'id' => $record->id,
                'status' => $record->status,
                'check_in_time' => $record->check_in_time,
                'check_out_time' => $record->check_out_time,
                'note' => $record->note,
            ] : null,
        ];
    }

    private function staffReportRow(User $user, ?Collection $records = null): array
    {
        $records ??= collect();
        $present = $records->where('status', 'Present')->count();
        $absent = $records->where('status', 'Absent')->count();
        $late = $records->where('status', 'Late')->count();
        $leave = $records->where('status', 'Leave')->count();
        $total = $records->count();

        return [
            'id' => $user->id,
            'staff_id' => $user->staff_id,
            'name' => $user->full_name ?: $user->username ?: 'Unknown staff',
            'username' => $user->username,
            'role' => $user->role?->name,
            'department' => $user->department,
            'position' => $user->position,
            'image' => $user->image,
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'rate' => $total > 0 ? (int) round(($present / $total) * 100) : 0,
            'absent_dates' => $this->collectDates($records, 'Absent'),
            'late_dates' => $this->collectDates($records, 'Late'),
            'leave_dates' => $this->collectDates($records, 'Leave'),
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

    private function staffUsersQuery(): Builder
    {
        return User::query()
            ->select('id', 'staff_id', 'full_name', 'username', 'phone', 'image', 'department', 'position', 'role_id', 'status')
            ->with('role:id,name')
            ->where(function (Builder $query) {
                $query
                    ->whereNull('status')
                    ->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->whereHas('role', function (Builder $query) {
                $query
                    ->whereRaw('LOWER(name) <> ?', ['teacher'])
                    ->whereRaw('LOWER(name) <> ?', ['student']);
            })
            ->orderByRaw("COALESCE(NULLIF(full_name, ''), username)");
    }

    private function buildSummary(string $date, ?Collection $records = null, ?int $total = null): array
    {
        $total ??= $this->staffUsersQuery()->count();

        if (!$this->attendanceTableExists()) {
            return [
                'total' => $total,
                'marked' => 0,
                'unmarked' => $total,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
            ];
        }

        if ($records === null) {
            $statusCounts = StaffAttendance::query()
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

    private function attendanceTableExists(): bool
    {
        return Schema::hasTable('staff_attendances');
    }
}
