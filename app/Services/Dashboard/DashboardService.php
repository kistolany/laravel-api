<?php

namespace App\Services\Dashboard;

use App\Models\Classes;
use App\Models\ClassSchedule;
use App\Models\ClassStudent;
use App\Models\LeaveRequest;
use App\Models\Major;
use App\Models\StaffAttendance;
use App\Models\StudentPayment;
use App\Models\Students;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Services\BaseService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService extends BaseService
{
    public function teacherStats(?Authenticatable $user): array
    {
        return $this->trace(__FUNCTION__, function () use ($user): array {
            $teacherId = $user?->teacher_id;
            $todayName = now()->format('l');

            if (!$teacherId) {
                return [
                    'teacher_id' => null,
                    'total_classes' => 0,
                    'total_subjects' => 0,
                    'total_schedules' => 0,
                    'today_schedules' => [],
                    'week_schedules' => [],
                    'today' => $todayName,
                ];
            }

            $weekSchedules = $this->teacherScheduleQuery($teacherId)
                ->orderByRaw("FIELD(class_schedules.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
                ->orderBy('shifts.name')
                ->get();

            $attendanceThisMonth = TeacherAttendance::where('teacher_id', $teacherId)
                ->whereBetween('attendance_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->select('attendance_date', 'status', 'check_in_time', 'check_out_time')
                ->orderBy('attendance_date')
                ->get();

            return [
                'teacher_id' => $teacherId,
                'total_classes' => ClassSchedule::where('teacher_id', $teacherId)->distinct('class_id')->count('class_id'),
                'total_subjects' => ClassSchedule::where('teacher_id', $teacherId)->distinct('subject_id')->count('subject_id'),
                'total_schedules' => ClassSchedule::where('teacher_id', $teacherId)->count(),
                'today_schedules' => $this->teacherScheduleQuery($teacherId)
                    ->where('class_schedules.day_of_week', $todayName)
                    ->orderBy('shifts.name')
                    ->get(),
                'week_schedules' => $weekSchedules,
                'by_day' => $weekSchedules->groupBy('day_of_week')->map(fn ($items) => $items->count())->toArray(),
                'today' => $todayName,
                'attendance_month' => [
                    'present' => $attendanceThisMonth->where('status', 'Present')->count(),
                    'absent' => $attendanceThisMonth->where('status', 'Absent')->count(),
                    'late' => $attendanceThisMonth->where('status', 'Late')->count(),
                    'leave' => $attendanceThisMonth->where('status', 'Leave')->count(),
                    'total' => $attendanceThisMonth->count(),
                ],
                'attendance_recent' => TeacherAttendance::where('teacher_id', $teacherId)
                    ->whereBetween('attendance_date', [now()->subDays(13)->startOfDay(), now()->endOfDay()])
                    ->select('attendance_date', 'status')
                    ->orderBy('attendance_date')
                    ->get()
                    ->map(fn ($row) => [
                        'date' => $row->attendance_date,
                        'status' => $row->status,
                    ]),
            ];
        });
    }

    public function stats(string $period): array
    {
        return $this->trace(__FUNCTION__, function () use ($period): array {
            return Cache::remember("dashboard:stats:v6:{$period}", now()->addSeconds(60), function () use ($period): array {
                $todayName = now()->format('l');
                $enrollment = $this->enrollmentStats($period);

                return [
                    'total_students' => Students::count(),
                    'active_students' => Students::whereIn('status', ['Active', 'active', 'enable'])->count(),
                    'male_students' => Students::where('gender', 'Male')->count(),
                    'female_students' => Students::where('gender', 'Female')->count(),
                    'total_teachers' => Teacher::count(),
                    'active_teachers' => Teacher::whereIn('status', ['Active', 'active', 'enable'])->count(),
                    'total_classes' => Classes::count(),
                    'active_classes' => Classes::where('is_active', true)->count(),
                    'total_majors' => Major::count(),
                    'total_subjects' => Subject::count(),
                    'total_schedules' => ClassSchedule::count(),
                    'pending_leave_requests' => LeaveRequest::where('status', 'pending')->count(),
                    'unpaid_payments' => StudentPayment::whereIn('status', ['pending', 'partial', 'overdue'])->count(),
                    'overdue_payments' => $this->overduePayments(),
                    'unassigned_students' => Students::whereIn('status', ['Active', 'active', 'enable'])->whereDoesntHave('classes')->count(),
                    'active_class_enrollments' => ClassStudent::whereIn('status', ['Active', 'active'])->count(),
                    'status_breakdown' => $this->studentStatusBreakdown(),
                    'by_major' => $this->studentsByMajor(),
                    'by_gender' => $this->studentsByGender(),
                    'by_month' => $this->studentsByMonth(),
                    'enrollment_daily' => $enrollment['trend'],
                    'enrollment_trend' => $enrollment['trend'],
                    'enrollment_period' => $period,
                    'enrollment_group' => $enrollment['group'],
                    'enrollment_period_label' => $enrollment['label'],
                    'recent_students' => $this->recentStudents(),
                    'class_occupancy' => $this->classOccupancy(),
                    'today_schedules' => $this->todaySchedules($todayName),
                    'payment_summary' => $this->paymentSummary(),
                    'staff_attendance_today' => $this->staffAttendanceToday(),
                    'attendance_trend' => $this->attendanceTrend(),
                    'schedules_by_day' => $this->schedulesByDay(),
                    'today' => $todayName,
                ];
            });
        });
    }

    private function teacherScheduleQuery(int $teacherId)
    {
        return DB::table('class_schedules')
            ->join('classes', 'class_schedules.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'class_schedules.subject_id', '=', 'subjects.id')
            ->leftJoin('shifts', 'class_schedules.shift_id', '=', 'shifts.id')
            ->leftJoin('rooms', 'class_schedules.room_id', '=', 'rooms.id')
            ->where('class_schedules.teacher_id', $teacherId)
            ->select(
                'class_schedules.id',
                'classes.name as class_name',
                'subjects.name as subject_name',
                'shifts.name as shift_name',
                'rooms.name as room_name',
                'class_schedules.day_of_week',
            );
    }

    private function overduePayments(): int
    {
        return StudentPayment::whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
    }

    private function paymentSummary()
    {
        return StudentPayment::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_due - amount_paid - discount) as balance'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status ?: 'unknown',
                'count' => (int) $row->count,
                'balance' => (float) $row->balance,
            ])
            ->values();
    }

    private function staffAttendanceToday()
    {
        return StaffAttendance::whereDate('attendance_date', now()->toDateString())
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ])
            ->values();
    }

    private function attendanceTrend()
    {
        $rows = DB::table('attendance_sessions')
            ->select(DB::raw('DATE(session_date) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('session_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
            ->groupBy(DB::raw('DATE(session_date)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return collect(range(6, 0))
            ->map(function (int $daysAgo) use ($rows): array {
                $date = now()->subDays($daysAgo)->toDateString();

                return [
                    'date' => $date,
                    'count' => (int) ($rows->get($date)?->count ?? 0),
                ];
            })
            ->values();
    }

    private function schedulesByDay()
    {
        $weekdayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $scheduleCounts = ClassSchedule::select('day_of_week', DB::raw('COUNT(*) as count'))
            ->groupBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');

        return collect($weekdayOrder)
            ->map(fn (string $day): array => [
                'day' => $day,
                'count' => (int) ($scheduleCounts->get($day)?->count ?? 0),
            ])
            ->values();
    }

    private function studentStatusBreakdown(): array
    {
        $rows = Students::select('status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('status')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'active' => (int) (($rows->get('Active')?->count ?? 0) + ($rows->get('active')?->count ?? 0) + ($rows->get('enable')?->count ?? 0)),
            'graduated' => (int) (($rows->get('Graduated')?->count ?? 0) + ($rows->get('graduated')?->count ?? 0)),
            'dropped' => (int) (($rows->get('Dropped')?->count ?? 0) + ($rows->get('dropped')?->count ?? 0)),
            'suspended' => (int) (($rows->get('Suspended')?->count ?? 0) + ($rows->get('suspended')?->count ?? 0)),
        ];
    }

    private function studentsByMajor()
    {
        return DB::table('students')
            ->join('academic_info', 'students.id', '=', 'academic_info.student_id')
            ->join('majors', 'academic_info.major_id', '=', 'majors.id')
            ->select('majors.name as major', DB::raw('COUNT(*) as count'))
            ->groupBy('majors.id', 'majors.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get();
    }

    private function studentsByGender(): array
    {
        $rows = Students::select('gender', DB::raw('COUNT(*) as count'))
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->get()
            ->keyBy('gender');

        return [
            'male' => (int) ($rows->get('Male')?->count ?? 0),
            'female' => (int) ($rows->get('Female')?->count ?? 0),
        ];
    }

    private function studentsByMonth()
    {
        return DB::table('students')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"),
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as sort_key"),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month', 'sort_key')
            ->orderBy('sort_key')
            ->get();
    }

    private function enrollmentStats(string $period): array
    {
        $group = $period === 'year' ? 'month' : 'day';
        $periodStart = match ($period) {
            'week' => now()->startOfWeek(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };
        $periodEnd = match ($period) {
            'week' => now()->endOfWeek(),
            'year' => now()->endOfYear(),
            default => now()->endOfMonth(),
        };
        $label = match ($period) {
            'week' => 'Daily student records for this week',
            'year' => 'Monthly student records for this year',
            default => 'Daily student records for this month',
        };

        if ($group === 'month') {
            $rows = DB::table('students')
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as bucket"), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->keyBy('bucket');

            $trend = collect(range(1, 12))
                ->map(function (int $month) use ($periodStart, $rows): array {
                    $date = $periodStart->copy()->month($month)->startOfMonth();
                    $bucket = $date->format('Y-m');

                    return [
                        'date' => $date->toDateString(),
                        'month' => $month,
                        'count' => (int) ($rows->get($bucket)?->count ?? 0),
                    ];
                })
                ->values();
        } else {
            $rows = DB::table('students')
                ->select(DB::raw('DATE(created_at) as bucket'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('bucket')
                ->get()
                ->keyBy('bucket');

            $trend = collect(range(0, $periodStart->diffInDays($periodEnd)))
                ->map(function (int $offset) use ($periodStart, $rows): array {
                    $date = $periodStart->copy()->addDays($offset);
                    $bucket = $date->toDateString();

                    return [
                        'date' => $bucket,
                        'day' => (int) $date->format('d'),
                        'count' => (int) ($rows->get($bucket)?->count ?? 0),
                    ];
                })
                ->values();
        }

        return compact('group', 'label', 'trend');
    }

    private function recentStudents()
    {
        return DB::table('students')
            ->leftJoin('academic_info', 'students.id', '=', 'academic_info.student_id')
            ->leftJoin('majors', 'academic_info.major_id', '=', 'majors.id')
            ->select(
                'students.id',
                'students.full_name_en',
                'students.full_name_kh',
                'students.gender',
                'students.status',
                'students.created_at',
                'majors.name as major_name'
            )
            ->orderByDesc('students.created_at')
            ->limit(5)
            ->get()
            ->map(fn ($student): array => [
                'id' => $student->id,
                'name' => $student->full_name_en ?: $student->full_name_kh ?: 'Unknown',
                'major' => $student->major_name,
                'status' => $student->status,
                'gender' => $student->gender,
                'enrolled_at' => $student->created_at,
            ]);
    }

    private function classOccupancy()
    {
        return DB::table('classes')
            ->leftJoin('class_students', function ($join) {
                $join->on('classes.id', '=', 'class_students.class_id')
                    ->whereIn('class_students.status', ['Active', 'active']);
            })
            ->select(
                'classes.id',
                'classes.name',
                DB::raw('COUNT(class_students.student_id) as enrolled')
            )
            ->groupBy('classes.id', 'classes.name')
            ->orderByDesc('enrolled')
            ->limit(8)
            ->get()
            ->map(fn ($class): array => [
                'id' => $class->id,
                'name' => $class->name,
                'enrolled' => (int) $class->enrolled,
                'max' => null,
                'pct' => null,
            ]);
    }

    private function todaySchedules(string $todayName)
    {
        return DB::table('class_schedules')
            ->join('classes', 'class_schedules.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'class_schedules.subject_id', '=', 'subjects.id')
            ->leftJoin('teachers', 'class_schedules.teacher_id', '=', 'teachers.id')
            ->leftJoin('shifts', 'class_schedules.shift_id', '=', 'shifts.id')
            ->leftJoin('rooms', 'class_schedules.room_id', '=', 'rooms.id')
            ->where('class_schedules.day_of_week', $todayName)
            ->select(
                'class_schedules.id',
                'classes.name as class_name',
                'subjects.name as subject_name',
                DB::raw("CONCAT(teachers.first_name, ' ', teachers.last_name) as teacher_name"),
                'shifts.name as shift_name',
                'rooms.name as room_name',
                'class_schedules.day_of_week',
            )
            ->orderBy('shifts.name')
            ->limit(8)
            ->get();
    }
}
