<?php

namespace App\Http\Controllers\ApiController\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\AttendanceSession;
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
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function teacherStats(Request $request)
    {
        $user      = $request->user();
        $teacherId = $user?->teacher_id;

        if (!$teacherId) {
            return $this->success([
                'teacher_id'       => null,
                'total_classes'    => 0,
                'total_subjects'   => 0,
                'total_schedules'  => 0,
                'today_schedules'  => [],
                'week_schedules'   => [],
                'today'            => now()->format('l'),
            ]);
        }

        // ── Counts for this teacher ───────────────────────────────────
        $totalClasses = ClassSchedule::where('teacher_id', $teacherId)
            ->distinct('class_id')
            ->count('class_id');

        $totalSubjects = ClassSchedule::where('teacher_id', $teacherId)
            ->distinct('subject_id')
            ->count('subject_id');

        $totalSchedules = ClassSchedule::where('teacher_id', $teacherId)->count();

        // ── Today's schedules for this teacher ────────────────────────
        $todayName = now()->format('l');
        $todaySchedules = DB::table('class_schedules')
            ->join('classes', 'class_schedules.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'class_schedules.subject_id', '=', 'subjects.id')
            ->leftJoin('shifts', 'class_schedules.shift_id', '=', 'shifts.id')
            ->where('class_schedules.teacher_id', $teacherId)
            ->where('class_schedules.day_of_week', $todayName)
            ->select(
                'class_schedules.id',
                'classes.name as class_name',
                'subjects.name as subject_name',
                'shifts.name as shift_name',
                'class_schedules.room',
                'class_schedules.day_of_week',
                'class_schedules.year_level',
                'class_schedules.semester',
            )
            ->orderBy('shifts.name')
            ->get();

        // ── Full week schedule for this teacher ───────────────────────
        $weekSchedules = DB::table('class_schedules')
            ->join('classes', 'class_schedules.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'class_schedules.subject_id', '=', 'subjects.id')
            ->leftJoin('shifts', 'class_schedules.shift_id', '=', 'shifts.id')
            ->where('class_schedules.teacher_id', $teacherId)
            ->select(
                'class_schedules.id',
                'classes.name as class_name',
                'subjects.name as subject_name',
                'shifts.name as shift_name',
                'class_schedules.room',
                'class_schedules.day_of_week',
                'class_schedules.year_level',
                'class_schedules.semester',
            )
            ->orderByRaw("FIELD(class_schedules.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->orderBy('shifts.name')
            ->get();

        // ── Sessions per day breakdown ────────────────────────────────
        $byDay = $weekSchedules
            ->groupBy('day_of_week')
            ->map(fn ($items) => $items->count())
            ->toArray();

        // ── Teacher attendance this month ─────────────────────────────
        $monthStart = now()->startOfMonth();
        $monthEnd   = now()->endOfMonth();

        $attendanceThisMonth = TeacherAttendance::where('teacher_id', $teacherId)
            ->whereBetween('attendance_date', [$monthStart, $monthEnd])
            ->select('attendance_date', 'status', 'check_in_time', 'check_out_time')
            ->orderBy('attendance_date')
            ->get();

        $attendanceCounts = [
            'present' => $attendanceThisMonth->where('status', 'Present')->count(),
            'absent'  => $attendanceThisMonth->where('status', 'Absent')->count(),
            'late'    => $attendanceThisMonth->where('status', 'Late')->count(),
            'leave'   => $attendanceThisMonth->where('status', 'Leave')->count(),
            'total'   => $attendanceThisMonth->count(),
        ];

        // Last 14 days strip (for the mini chart)
        $recentAttendance = TeacherAttendance::where('teacher_id', $teacherId)
            ->whereBetween('attendance_date', [now()->subDays(13)->startOfDay(), now()->endOfDay()])
            ->select('attendance_date', 'status')
            ->orderBy('attendance_date')
            ->get()
            ->map(fn ($r) => [
                'date'   => $r->attendance_date,
                'status' => $r->status,
            ]);

        return $this->success([
            'teacher_id'         => $teacherId,
            'total_classes'      => $totalClasses,
            'total_subjects'     => $totalSubjects,
            'total_schedules'    => $totalSchedules,
            'today_schedules'    => $todaySchedules,
            'week_schedules'     => $weekSchedules,
            'by_day'             => $byDay,
            'today'              => $todayName,
            'attendance_month'   => $attendanceCounts,
            'attendance_recent'  => $recentAttendance,
        ]);
    }

    public function stats(Request $request)
    {
        $period = strtolower((string) $request->query('period', 'month'));
        if (!in_array($period, ['week', 'month', 'year'], true)) {
            $period = 'month';
        }

        $data = Cache::remember("dashboard:stats:v5:{$period}", now()->addSeconds(60), function () use ($period) {
        // ── Counts ────────────────────────────────────────────────────
        $totalStudents   = Students::count();
        $activeStudents  = Students::whereIn('status', ['Active', 'active', 'enable'])->count();
        $maleStudents    = Students::where('gender', 'Male')->count();
        $femaleStudents  = Students::where('gender', 'Female')->count();
        $totalTeachers   = Teacher::count();
        $activeTeachers  = Teacher::whereIn('status', ['Active', 'active', 'enable'])->count();
        $totalClasses    = Classes::count();
        $activeClasses   = Classes::where('is_active', true)->count();
        $totalMajors     = Major::count();
        $totalSubjects   = Subject::count();
        $totalSchedules  = ClassSchedule::count();
        $pendingLeaveRequests = LeaveRequest::where('status', 'pending')->count();
        $unpaidPayments = StudentPayment::whereIn('status', ['pending', 'partial', 'overdue'])->count();
        $overduePayments = StudentPayment::whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
        $unassignedStudents = Students::whereIn('status', ['Active', 'active', 'enable'])
            ->whereDoesntHave('classes')
            ->count();
        $activeClassEnrollments = ClassStudent::whereIn('status', ['Active', 'active'])->count();

        $paymentSummary = StudentPayment::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount_due - amount_paid - discount) as balance'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status ?: 'unknown',
                'count' => (int) $row->count,
                'balance' => (float) $row->balance,
            ])
            ->values();

        $staffAttendanceToday = StaffAttendance::whereDate('attendance_date', now()->toDateString())
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ])
            ->values();

        $attendanceSessionsLast7Days = DB::table('attendance_sessions')
            ->select(DB::raw('DATE(session_date) as date'), DB::raw('COUNT(*) as count'))
            ->whereBetween('session_date', [now()->subDays(6)->toDateString(), now()->toDateString()])
            ->groupBy(DB::raw('DATE(session_date)'))
            ->orderBy('date')
            ->get();

        $attendanceSessionsByDate = $attendanceSessionsLast7Days->keyBy('date');
        $attendanceTrend = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($attendanceSessionsByDate) {
                $date = now()->subDays($daysAgo)->toDateString();
                return [
                    'date' => $date,
                    'count' => (int) ($attendanceSessionsByDate->get($date)?->count ?? 0),
                ];
            })
            ->values();

        $weekdayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $scheduleCounts = ClassSchedule::select('day_of_week', DB::raw('COUNT(*) as count'))
            ->groupBy('day_of_week')
            ->get()
            ->keyBy('day_of_week');
        $schedulesByDay = collect($weekdayOrder)
            ->map(fn ($day) => [
                'day' => $day,
                'count' => (int) ($scheduleCounts->get($day)?->count ?? 0),
            ])
            ->values();

        // ── Student status breakdown ──────────────────────────────────
        $statusBreakdown = Students::select('status', DB::raw('COUNT(*) as count'))
            ->whereNotNull('status')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // ── Students by major (top 6) ─────────────────────────────────
        $byMajor = DB::table('students')
            ->join('academic_info', 'students.id', '=', 'academic_info.student_id')
            ->join('majors', 'academic_info.major_id', '=', 'majors.id')
            ->select('majors.name as major', DB::raw('COUNT(*) as count'))
            ->groupBy('majors.id', 'majors.name')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // ── Students by gender ────────────────────────────────────────
        $byGender = Students::select('gender', DB::raw('COUNT(*) as count'))
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->get()
            ->keyBy('gender');

        // ── Monthly enrollment (last 6 months) ────────────────────────
        $byMonth = DB::table('students')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"),
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as sort_key"),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month', 'sort_key')
            ->orderBy('sort_key')
            ->get();

        $enrollmentGroup = $period === 'year' ? 'month' : 'day';
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
        $periodLabel = match ($period) {
            'week' => 'Daily student records for this week',
            'year' => 'Monthly student records for this year',
            default => 'Daily student records for this month',
        };

        if ($enrollmentGroup === 'month') {
            $enrollmentRows = DB::table('students')
                ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as bucket"), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->keyBy('bucket');

            $enrollmentTrend = collect(range(1, 12))
                ->map(function (int $month) use ($periodStart, $enrollmentRows) {
                    $date = $periodStart->copy()->month($month)->startOfMonth();
                    $bucket = $date->format('Y-m');
                    return [
                        'date' => $date->toDateString(),
                        'month' => $month,
                        'count' => (int) ($enrollmentRows->get($bucket)?->count ?? 0),
                    ];
                })
                ->values();
        } else {
            $enrollmentRows = DB::table('students')
                ->select(DB::raw('DATE(created_at) as bucket'), DB::raw('COUNT(*) as count'))
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('bucket')
                ->get()
                ->keyBy('bucket');

            $days = $periodStart->diffInDays($periodEnd) + 1;
            $enrollmentTrend = collect(range(0, $days - 1))
                ->map(function (int $offset) use ($periodStart, $enrollmentRows) {
                    $date = $periodStart->copy()->addDays($offset);
                    $bucket = $date->toDateString();
                    $day = (int) $date->format('d');
                    return [
                        'date' => $bucket,
                        'day' => $day,
                        'count' => (int) ($enrollmentRows->get($bucket)?->count ?? 0),
                    ];
                })
                ->values();
        }

        $enrollmentDaily = $enrollmentTrend;

        // ── Recent enrollments (last 5 students) ─────────────────────
        $recentStudents = DB::table('students')
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
            ->map(fn($s) => [
                'id'          => $s->id,
                'name'        => $s->full_name_en ?: $s->full_name_kh ?: 'Unknown',
                'major'       => $s->major_name,
                'status'      => $s->status,
                'gender'      => $s->gender,
                'enrolled_at' => $s->created_at,
            ]);

        // ── Class occupancy (top 8 classes) ──────────────────────────
        $classOccupancy = DB::table('classes')
            ->leftJoin('class_students', 'classes.id', '=', 'class_students.class_id')
            ->select(
                'classes.id',
                'classes.name',
                'classes.max_students',
                DB::raw('COUNT(class_students.student_id) as enrolled')
            )
            ->groupBy('classes.id', 'classes.name', 'classes.max_students')
            ->orderByDesc('enrolled')
            ->limit(8)
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'enrolled' => (int) $c->enrolled,
                'max'      => $c->max_students ? (int) $c->max_students : null,
                'pct'      => $c->max_students > 0
                    ? round(($c->enrolled / $c->max_students) * 100)
                    : null,
            ]);

        // ── Today's schedules ─────────────────────────────────────────
        $todayName = now()->format('l'); // e.g. "Monday"
        $todaySchedules = DB::table('class_schedules')
            ->join('classes', 'class_schedules.class_id', '=', 'classes.id')
            ->leftJoin('subjects', 'class_schedules.subject_id', '=', 'subjects.id')
            ->leftJoin('teachers', 'class_schedules.teacher_id', '=', 'teachers.id')
            ->leftJoin('shifts', 'class_schedules.shift_id', '=', 'shifts.id')
            ->where('class_schedules.day_of_week', $todayName)
            ->select(
                'class_schedules.id',
                'classes.name as class_name',
                'subjects.name as subject_name',
                DB::raw("CONCAT(teachers.first_name, ' ', teachers.last_name) as teacher_name"),
                'shifts.name as shift_name',
                'class_schedules.room',
                'class_schedules.day_of_week',
            )
            ->orderBy('shifts.name')
            ->limit(8)
            ->get();

        return [
            'total_students'   => $totalStudents,
            'active_students'  => $activeStudents,
            'male_students'    => $maleStudents,
            'female_students'  => $femaleStudents,
            'total_teachers'   => $totalTeachers,
            'active_teachers'  => $activeTeachers,
            'total_classes'    => $totalClasses,
            'active_classes'   => $activeClasses,
            'total_majors'     => $totalMajors,
            'total_subjects'   => $totalSubjects,
            'total_schedules'  => $totalSchedules,
            'pending_leave_requests' => $pendingLeaveRequests,
            'unpaid_payments' => $unpaidPayments,
            'overdue_payments' => $overduePayments,
            'unassigned_students' => $unassignedStudents,
            'active_class_enrollments' => $activeClassEnrollments,
            'status_breakdown' => [
                'active'    => (int) (($statusBreakdown->get('Active')?->count ?? 0) + ($statusBreakdown->get('active')?->count ?? 0) + ($statusBreakdown->get('enable')?->count ?? 0)),
                'graduated' => (int) (($statusBreakdown->get('Graduated')?->count ?? 0) + ($statusBreakdown->get('graduated')?->count ?? 0)),
                'dropped'   => (int) (($statusBreakdown->get('Dropped')?->count ?? 0) + ($statusBreakdown->get('dropped')?->count ?? 0)),
                'suspended' => (int) (($statusBreakdown->get('Suspended')?->count ?? 0) + ($statusBreakdown->get('suspended')?->count ?? 0)),
            ],
            'by_major'           => $byMajor,
            'by_gender'          => [
                'male'   => (int)($byGender->get('Male')?->count   ?? 0),
                'female' => (int)($byGender->get('Female')?->count ?? 0),
            ],
            'by_month'           => $byMonth,
            'enrollment_daily'    => $enrollmentDaily,
            'enrollment_trend'    => $enrollmentTrend,
            'enrollment_period'   => $period,
            'enrollment_group'    => $enrollmentGroup,
            'enrollment_period_label' => $periodLabel,
            'recent_students'    => $recentStudents,
            'class_occupancy'    => $classOccupancy,
            'today_schedules'    => $todaySchedules,
            'payment_summary'     => $paymentSummary,
            'staff_attendance_today' => $staffAttendanceToday,
            'attendance_trend'    => $attendanceTrend,
            'schedules_by_day'    => $schedulesByDay,
            'today'              => $todayName,
        ];
        });

        return $this->success($data);
    }
}
