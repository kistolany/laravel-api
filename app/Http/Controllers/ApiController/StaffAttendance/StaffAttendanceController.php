<?php

namespace App\Http\Controllers\ApiController\StaffAttendance;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffAttendanceController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $staffUsers = $this->staffUsersQuery()->get();

        if (!$this->attendanceTableExists()) {
            $staff = $staffUsers
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'staff_id' => $user->staff_id,
                    'name' => $user->full_name ?: $user->username ?: 'Unknown staff',
                    'username' => $user->username,
                    'role' => $user->role?->name,
                    'department' => $user->department,
                    'position' => $user->position,
                    'phone' => $user->phone,
                    'image' => $user->image,
                    'attendance' => null,
                ])
                ->values();

            return $this->success([
                'date' => $date,
                'staff' => $staff,
                'summary' => $this->buildSummary($date, collect(), $staffUsers->count()),
            ]);
        }

        $attendanceRecords = StaffAttendance::query()
            ->select('id', 'user_id', 'status', 'check_in_time', 'check_out_time', 'note')
            ->where('attendance_date', $date)
            ->get()
            ->keyBy('user_id');

        $staff = $staffUsers
            ->map(function (User $user) use ($attendanceRecords) {
                $record = $attendanceRecords->get($user->id);

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
            })
            ->values();

        return $this->success([
            'date' => $date,
            'staff' => $staff,
            'summary' => $this->buildSummary($date, $attendanceRecords->values(), $staffUsers->count()),
        ]);
    }

    public function bulk(Request $request): JsonResponse
    {
        if (!$this->attendanceTableExists()) {
            return response()->json([
                'message' => 'Staff attendance storage is not ready yet. Run the latest database migrations first.',
                'errors' => [
                    'migration' => ['Staff attendance storage is not ready yet. Run the latest database migrations first.'],
                ],
            ], 503);
        }

        $data = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.user_id' => 'required|exists:users,id',
            'records.*.status' => 'required|in:Present,Absent,Late,Leave',
            'records.*.check_in_time' => 'nullable|date_format:H:i',
            'records.*.check_out_time' => 'nullable|date_format:H:i',
            'records.*.note' => 'nullable|string|max:255',
        ]);

        $requestedIds = collect($data['records'])
            ->pluck('user_id')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        $staffIds = $this->staffUsersQuery()
            ->whereIn('id', $requestedIds)
            ->pluck('id')
            ->map(fn ($value) => (int) $value);

        if ($staffIds->count() !== $requestedIds->count()) {
            return response()->json([
                'message' => 'One or more selected users are not eligible for staff attendance.',
                'errors' => [
                    'records' => ['One or more selected users are not eligible for staff attendance.'],
                ],
            ], 422);
        }

        $userId = Auth::id();
        $now = now();

        $rows = collect($data['records'])
            ->map(fn (array $record) => [
                'user_id' => (int) $record['user_id'],
                'attendance_date' => $data['date'],
                'status' => $record['status'],
                'check_in_time' => $record['check_in_time'] ?? null,
                'check_out_time' => $record['check_out_time'] ?? null,
                'note' => $record['note'] ?? null,
                'recorded_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        StaffAttendance::upsert(
            $rows,
            ['user_id', 'attendance_date'],
            ['status', 'check_in_time', 'check_out_time', 'note', 'recorded_by', 'updated_at']
        );

        return $this->success([
            'date' => $data['date'],
            'saved' => count($rows),
            'summary' => $this->buildSummary($data['date']),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        if (!$this->attendanceTableExists()) {
            return $this->success([
                'from' => $from,
                'to' => $to,
                'history' => [],
            ]);
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

        return $this->success([
            'from' => $from,
            'to' => $to,
            'history' => $rows,
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $staffUsers = $this->staffUsersQuery()->get();

        if (!$this->attendanceTableExists()) {
            $staff = $staffUsers
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'staff_id' => $user->staff_id,
                    'name' => $user->full_name ?: $user->username ?: 'Unknown staff',
                    'username' => $user->username,
                    'role' => $user->role?->name,
                    'department' => $user->department,
                    'position' => $user->position,
                    'image' => $user->image,
                    'total' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0,
                    'leave' => 0,
                    'rate' => 0,
                    'absent_dates' => collect(),
                    'late_dates' => collect(),
                    'leave_dates' => collect(),
                ])
                ->values();

            return $this->success([
                'from' => $from,
                'to' => $to,
                'staff' => $staff,
            ]);
        }

        $records = StaffAttendance::query()
            ->select('user_id', 'attendance_date', 'status')
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('user_id');

        $staff = $staffUsers
            ->map(function (User $user) use ($records) {
                $userRecords = $records->get($user->id, collect());

                $present = $userRecords->where('status', 'Present')->count();
                $absent = $userRecords->where('status', 'Absent')->count();
                $late = $userRecords->where('status', 'Late')->count();
                $leave = $userRecords->where('status', 'Leave')->count();
                $total = $userRecords->count();

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
                    'absent_dates' => $this->collectDates($userRecords, 'Absent'),
                    'late_dates' => $this->collectDates($userRecords, 'Late'),
                    'leave_dates' => $this->collectDates($userRecords, 'Leave'),
                ];
            })
            ->values();

        return $this->success([
            'from' => $from,
            'to' => $to,
            'staff' => $staff,
        ]);
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
