<?php

namespace App\Http\Controllers\ApiController\TeacherAttendance;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class TeacherAttendanceController extends Controller
{
    use ApiResponseTrait;

    // GET /teacher-attendances?date=YYYY-MM-DD
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        $attendanceRecords = TeacherAttendance::query()
            ->select('id', 'teacher_id', 'status', 'check_in_time', 'check_out_time', 'note')
            ->where('attendance_date', $date)
            ->get()
            ->keyBy('teacher_id');

        $teacherModels = Teacher::query()
            ->select('id', 'teacher_id', 'first_name', 'last_name', 'gender', 'position', 'major_id', 'subject_id', 'image')
            ->with(['major:id,name', 'subject:id,name'])
            ->orderBy('first_name')
            ->get();

        $teachers = $teacherModels
            ->map(function (Teacher $t) use ($attendanceRecords) {
                $record = $attendanceRecords->get($t->id);

                return [
                    'id'             => $t->id,
                    'teacher_id'     => $t->teacher_id,
                    'name'           => trim($t->first_name . ' ' . $t->last_name),
                    'gender'         => $t->gender,
                    'position'       => $t->position,
                    'major'          => $t->major?->name ?? null,
                    'subject'        => $t->subject?->name ?? null,
                    'image'          => $t->image,
                    'attendance'     => $record ? [
                        'id'             => $record->id,
                        'status'         => $record->status,
                        'check_in_time'  => $record->check_in_time,
                        'check_out_time' => $record->check_out_time,
                        'note'           => $record->note,
                    ] : null,
                ];
            });

        $summary = $this->buildSummary($date, $attendanceRecords->values(), $teacherModels->count());

        return $this->success([
            'date'     => $date,
            'teachers' => $teachers,
            'summary'  => $summary,
        ]);
    }

    // POST /teacher-attendances/bulk  — upsert a full day's records
    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'                   => 'required|date',
            'records'                => 'required|array|min:1',
            'records.*.teacher_id'   => 'required|exists:teachers,id',
            'records.*.status'       => 'required|in:Present,Absent,Late,Leave',
            'records.*.check_in_time'  => 'nullable|date_format:H:i',
            'records.*.check_out_time' => 'nullable|date_format:H:i',
            'records.*.note'           => 'nullable|string|max:255',
        ]);

        $userId = Auth::id();
        $now    = now();

        $rows = collect($data['records'])->map(fn ($r) => [
            'teacher_id'      => $r['teacher_id'],
            'attendance_date' => $data['date'],
            'status'          => $r['status'],
            'check_in_time'   => $r['check_in_time']  ?? null,
            'check_out_time'  => $r['check_out_time'] ?? null,
            'note'            => $r['note'] ?? null,
            'recorded_by'     => $userId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ])->all();

        TeacherAttendance::upsert(
            $rows,
            ['teacher_id', 'attendance_date'],
            ['status', 'check_in_time', 'check_out_time', 'note', 'recorded_by', 'updated_at']
        );

        return $this->success([
            'date'    => $data['date'],
            'saved'   => count($rows),
            'summary' => $this->buildSummary($data['date']),
        ]);
    }

    // GET /teacher-attendances/history?from=YYYY-MM-DD&to=YYYY-MM-DD
    public function history(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $rows = DB::table('teacher_attendances')
            ->whereBetween('attendance_date', [$from, $to])
            ->select(
                'attendance_date as date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status='Present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN status='Absent'  THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN status='Late'    THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN status='Leave'   THEN 1 ELSE 0 END) as `leave`"),
            )
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get();

        return $this->success([
            'from'    => $from,
            'to'      => $to,
            'history' => $rows,
        ]);
    }

    // GET /teacher-attendances/report?from=YYYY-MM-DD&to=YYYY-MM-DD
    public function report(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to   = $request->query('to',   now()->toDateString());

        $teachers = Teacher::query()
            ->select('id', 'first_name', 'last_name', 'position', 'major_id', 'image')
            ->with(['major:id,name'])
            ->orderBy('first_name')
            ->get();

        $records = TeacherAttendance::query()
            ->select('teacher_id', 'attendance_date', 'status')
            ->whereBetween('attendance_date', [$from, $to])
            ->orderBy('attendance_date')
            ->get()
            ->groupBy('teacher_id');

        $data = $teachers->map(function (Teacher $t) use ($records, $from, $to) {
            $teacherRecords = $records->get($t->id, collect());

            $present = $teacherRecords->where('status', 'Present')->count();
            $absent  = $teacherRecords->where('status', 'Absent')->count();
            $late    = $teacherRecords->where('status', 'Late')->count();
            $leave   = $teacherRecords->where('status', 'Leave')->count();
            $total   = $teacherRecords->count();

            $absentDates = $teacherRecords->where('status', 'Absent')
                ->pluck('attendance_date')
                ->map(fn($d) => is_string($d) ? $d : $d->format('Y-m-d'))
                ->values();

            $leaveDates = $teacherRecords->where('status', 'Leave')
                ->pluck('attendance_date')
                ->map(fn($d) => is_string($d) ? $d : $d->format('Y-m-d'))
                ->values();

            $lateDates = $teacherRecords->where('status', 'Late')
                ->pluck('attendance_date')
                ->map(fn($d) => is_string($d) ? $d : $d->format('Y-m-d'))
                ->values();

            return [
                'id'           => $t->id,
                'name'         => trim($t->first_name . ' ' . $t->last_name),
                'position'     => $t->position,
                'major'        => $t->major?->name,
                'image'        => $t->image,
                'total'        => $total,
                'present'      => $present,
                'absent'       => $absent,
                'late'         => $late,
                'leave'        => $leave,
                'rate'         => $total > 0 ? round(($present / $total) * 100) : 0,
                'absent_dates' => $absentDates,
                'leave_dates'  => $leaveDates,
                'late_dates'   => $lateDates,
            ];
        });

        return $this->success([
            'from'     => $from,
            'to'       => $to,
            'teachers' => $data,
        ]);
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
                'total'     => $total,
                'marked'    => $marked,
                'unmarked'  => max(0, $total - $marked),
                'present'   => (int) ($statusCounts->get('Present') ?? 0),
                'absent'    => (int) ($statusCounts->get('Absent') ?? 0),
                'late'      => (int) ($statusCounts->get('Late') ?? 0),
                'leave'     => (int) ($statusCounts->get('Leave') ?? 0),
            ];
        }

        return [
            'total'     => $total,
            'marked'    => $records->count(),
            'unmarked'  => max(0, $total - $records->count()),
            'present'   => $records->where('status', 'Present')->count(),
            'absent'    => $records->where('status', 'Absent')->count(),
            'late'      => $records->where('status', 'Late')->count(),
            'leave'     => $records->where('status', 'Leave')->count(),
        ];
    }
}
