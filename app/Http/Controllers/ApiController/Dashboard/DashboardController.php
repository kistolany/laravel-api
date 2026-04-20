<?php

namespace App\Http\Controllers\ApiController\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\ClassSchedule;
use App\Models\Major;
use App\Models\Students;
use App\Models\Subject;
use App\Models\Teacher;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function stats()
    {
        // ── Counts ────────────────────────────────────────────────────
        $totalStudents   = Students::count();
        $activeStudents  = Students::where('status', 'Active')->count();
        $maleStudents    = Students::where('gender', 'Male')->count();
        $femaleStudents  = Students::where('gender', 'Female')->count();
        $totalTeachers   = Teacher::count();
        $totalClasses    = Classes::count();
        $activeClasses   = Classes::where('is_active', true)->count();
        $totalMajors     = Major::count();
        $totalSubjects   = Subject::count();
        $totalSchedules  = ClassSchedule::count();

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

        return $this->success([
            'total_students'   => $totalStudents,
            'active_students'  => $activeStudents,
            'male_students'    => $maleStudents,
            'female_students'  => $femaleStudents,
            'total_teachers'   => $totalTeachers,
            'total_classes'    => $totalClasses,
            'active_classes'   => $activeClasses,
            'total_majors'     => $totalMajors,
            'total_subjects'   => $totalSubjects,
            'total_schedules'  => $totalSchedules,
            'status_breakdown' => [
                'active'    => (int)($statusBreakdown->get('Active')?->count    ?? 0),
                'graduated' => (int)($statusBreakdown->get('Graduated')?->count ?? 0),
                'dropped'   => (int)($statusBreakdown->get('Dropped')?->count   ?? 0),
                'suspended' => (int)($statusBreakdown->get('Suspended')?->count ?? 0),
            ],
            'by_major'           => $byMajor,
            'by_gender'          => [
                'male'   => (int)($byGender->get('Male')?->count   ?? 0),
                'female' => (int)($byGender->get('Female')?->count ?? 0),
            ],
            'by_month'           => $byMonth,
            'recent_students'    => $recentStudents,
            'class_occupancy'    => $classOccupancy,
            'today_schedules'    => $todaySchedules,
            'today'              => $todayName,
        ]);
    }
}
