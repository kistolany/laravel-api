<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\TeacherAttendance;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use App\Models\Students;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponseTrait;

class LeaveRequestController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeaveRequest::query()
            ->leftJoin('academic_info as requester_academic_info', function ($join) {
                $join->on('leave_requests.requester_id', '=', 'requester_academic_info.student_id')
                    ->where('leave_requests.requester_type', '=', 'student');
            })
            ->leftJoin('majors as student_majors', 'requester_academic_info.major_id', '=', 'student_majors.id')
            ->leftJoin('teachers as requester_teachers', function ($join) {
                $join->on('leave_requests.requester_id', '=', 'requester_teachers.id')
                    ->where('leave_requests.requester_type', '=', 'teacher');
            })
            ->leftJoin('majors as teacher_majors', 'requester_teachers.major_id', '=', 'teacher_majors.id')
            ->leftJoin('subjects as teacher_subjects', 'requester_teachers.subject_id', '=', 'teacher_subjects.id')
            ->select('leave_requests.*')
            ->selectRaw('COALESCE(student_majors.name, teacher_majors.name) as major_name')
            ->selectRaw('requester_academic_info.stage as year')
            ->selectRaw('requester_academic_info.batch_year as batch_year')
            ->selectRaw('teacher_subjects.name as subject_name')
            ->selectRaw('requester_teachers.position as position')
            ->selectRaw('requester_teachers.role as teacher_role')
            ->selectRaw('requester_teachers.teacher_id as teacher_code');

        // 1. Apply Automatic Restrictions based on Role
        if ($user instanceof \App\Models\Teacher) {
            // Logged in via Teacher guard
            $query->where('leave_requests.requester_type', 'teacher')->where('leave_requests.requester_id', $user->id);
        } elseif ($user instanceof \App\Models\User) {
            // Logged in via User guard (Standard account)
            if ($user->hasRole('Student')) {
                // Students only see their own requests
                $studentId = $user->student_id;
                if (!$studentId) {
                    $studentId = \App\Models\Students::where('phone', $user->phone)->value('id');
                }
                
                if ($studentId) {
                    $query->where('leave_requests.requester_type', 'student')->where('leave_requests.requester_id', $studentId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->hasRole('Teacher')) {
                // Teachers logged in via User account
                $teacher = $this->resolveTeacherForUser($user);

                if ($teacher) {
                    $query->where('leave_requests.requester_type', 'teacher')->where('leave_requests.requester_id', $teacher->id);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        // 2. Apply Administrative Filters (Only for Admin/Staff)
        $isAdmin = ($user instanceof \App\Models\User && ($user->hasRole('Admin') || $user->hasRole('Staff')));

        if ($request->filled('requester_type')) {
            $query->where('leave_requests.requester_type', $request->requester_type);
        }

        if ($request->filled('role') && $isAdmin) {
            $role = $request->role;
            if ($role === 'Student') {
                $query->where('leave_requests.requester_type', 'student');
            } else {
                $query->where('leave_requests.requester_type', 'teacher')
                    ->whereIn('leave_requests.requester_id', function ($q) use ($role) {
                        $q->select('id')->from('teachers')->where('role', $role);
                    });
            }
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_requests.leave_type', $request->leave_type);
        }
        if ($request->filled('status')) {
            $query->where('leave_requests.status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('leave_requests.requester_name', 'like', "%{$search}%")
                    ->orWhere('leave_requests.requester_name_kh', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $paginated = $query->orderByDesc('leave_requests.created_at')->paginate($perPage);
        
        return $this->success(
            \App\DTOs\PaginatedResult::fromPaginator($paginated), 
            'Leave requests retrieved successfully.'
        );
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // 1. Auto-assign requester details based on the logged-in user
        if ($user instanceof \App\Models\Teacher) {
            // Logged in as Teacher
            $request->merge([
                'requester_type' => 'teacher',
                'requester_id' => $user->id,
                'requester_name' => $user->full_name ?? ($user->first_name . ' ' . $user->last_name)
            ]);
        } elseif ($user instanceof \App\Models\User && $user->hasRole('Student')) {
            // Logged in as Student
            $student = $user->student;
            if (!$student) {
                $student = \App\Models\Students::where('phone', $user->phone)->first();
            }

            if ($student) {
                $request->merge([
                    'requester_type' => 'student',
                    'requester_id' => $student->id,
                    'requester_name' => $student->full_name_en,
                    'requester_name_kh' => $student->full_name_kh
                ]);
            }
        } elseif ($user instanceof \App\Models\User && $user->hasRole('Teacher')) {
            // Teachers logged in through the standard User account.
            $teacher = $this->resolveTeacherForUser($user);

            if ($teacher) {
                $request->merge([
                    'requester_type' => 'teacher',
                    'requester_id' => $teacher->id,
                    'requester_name' => $teacher->full_name,
                    'requester_name_kh' => $request->input('requester_name_kh', ''),
                ]);
            }
        }

        $validated = $request->validate([
            'requester_type' => 'required|string|in:student,teacher',
            'requester_id' => 'required|integer',
            'requester_name' => 'nullable|string',
            'requester_name_kh' => 'nullable|string',
            'leave_type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'reason' => 'nullable|string',
        ]);
        
        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $days = $start->diffInDays($end) + 1;
        
        $validated['days'] = $days;
        $validated['status'] = 'pending';
        
        $leave = LeaveRequest::create($validated);
        $this->pushTeacherLeaveNotification($leave, $request);
        
        return response()->json(['message' => 'Created successfully', 'data' => $leave], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected,cancelled'
        ]);

        $leave = LeaveRequest::findOrFail($id);
        
        $wasNotApproved = $leave->status !== 'approved';
        $isApproving = $validated['status'] === 'approved';
        
        $leave->status = $validated['status'];
        $leave->save();

        if ($wasNotApproved && $isApproving) {
            $this->generateAutoAttendance($leave);
        }

        $this->pushLeaveDecisionNotification($leave, $request);

        return response()->json(['message' => 'Status updated successfully', 'data' => $leave]);
    }

    public function destroy($id)
    {
        $leave = LeaveRequest::findOrFail($id);
        $leave->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    private function generateAutoAttendance(LeaveRequest $leave)
    {
        $period = CarbonPeriod::create($leave->start_date, $leave->end_date);
        
        if ($leave->requester_type === 'teacher') {
            $upserts = [];
            foreach ($period as $date) {
                $upserts[] = [
                    'teacher_id' => $leave->requester_id,
                    'attendance_date' => $date->format('Y-m-d'),
                    'status' => 'Leave',
                    'note' => 'Auto-generated from Leave Request',
                    'check_in_time' => null,
                    'check_out_time' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            TeacherAttendance::upsert($upserts, ['teacher_id', 'attendance_date'], ['status', 'note', 'updated_at']);
        } elseif ($leave->requester_type === 'student') {
            // 1. Find all classes the student is in
            $classIds = \App\Models\ClassStudent::where('student_id', $leave->requester_id)
                ->where('status', 'Active')
                ->pluck('class_id');
            
            // 2. Find all sessions for these classes in the date range
            $sessions = \App\Models\AttendanceSession::whereIn('class_id', $classIds)
                ->whereBetween('session_date', [$leave->start_date, $leave->end_date])
                ->get();
            
            // 3. Create or update attendance record for each session
            foreach ($sessions as $session) {
                \App\Models\AttendanceRecord::updateOrCreate(
                    [
                        'attendance_session_id' => $session->id,
                        'student_id' => $leave->requester_id
                    ],
                    [
                        'status' => 'Leave',
                        'note' => 'Auto-generated from Approved Leave Request (ID: ' . $leave->id . ')'
                    ]
                );
            }
        }
    }

    private function pushTeacherLeaveNotification(LeaveRequest $leave, Request $request): void
    {
        if ($leave->requester_type !== 'teacher') {
            return;
        }

        $name = trim((string) ($leave->requester_name ?: 'A teacher'));
        $type = trim((string) ($leave->leave_type ?: 'leave'));
        $dates = trim((string) $leave->start_date) . ' to ' . trim((string) $leave->end_date);

        DB::table('push_notifications')->insert([
            'title'      => 'New Teacher Leave Request',
            'body'       => "{$name} submitted a {$type} request for {$dates}.",
            'audience'   => 'admin',
            'priority'   => 'warning',
            'sent_by'    => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pushLeaveDecisionNotification(LeaveRequest $leave, Request $request): void
    {
        $targetUserId = $this->resolveLeaveRequesterUserId($leave);

        if (! $targetUserId) {
            return;
        }

        $status = strtolower((string) $leave->status);
        $statusLabel = $status === 'approved' ? 'Approved' : ($status === 'rejected' ? 'Rejected' : ucfirst($status));
        $startDate = $leave->start_date instanceof Carbon ? $leave->start_date->format('Y-m-d') : (string) $leave->start_date;
        $endDate = $leave->end_date instanceof Carbon ? $leave->end_date->format('Y-m-d') : (string) $leave->end_date;

        DB::table('push_notifications')->insert([
            'title'          => "Leave Request {$statusLabel}",
            'body'           => "Your {$leave->leave_type} request from {$startDate} to {$endDate} was {$status}.",
            'audience'       => 'all',
            'priority'       => $status === 'rejected' ? 'warning' : 'info',
            'sent_by'        => $request->user()?->id,
            'target_user_id' => $targetUserId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function resolveLeaveRequesterUserId(LeaveRequest $leave): ?int
    {
        $column = match ($leave->requester_type) {
            'teacher' => 'teacher_id',
            'student' => 'student_id',
            default => null,
        };

        if (! $column) {
            return null;
        }

        $userId = DB::table('users')
            ->where($column, $leave->requester_id)
            ->where('status', 'Active')
            ->value('id');

        return $userId ? (int) $userId : null;
    }

    private function resolveTeacherForUser(\App\Models\User $user): ?\App\Models\Teacher
    {
        if ($user->teacher_id) {
            return \App\Models\Teacher::find($user->teacher_id);
        }

        $username = trim((string) $user->username);
        if ($username !== '') {
            $teacher = \App\Models\Teacher::where('username', $username)
                ->orWhere('email', $username)
                ->first();

            if ($teacher) {
                return $this->linkTeacherToUser($user, $teacher);
            }
        }

        $phone = trim((string) $user->phone);
        if ($phone !== '') {
            $teacher = \App\Models\Teacher::where('phone_number', $phone)->first();

            if ($teacher) {
                return $this->linkTeacherToUser($user, $teacher);
            }
        }

        $fullName = strtolower(preg_replace('/\s+/', ' ', trim((string) $user->full_name)));
        if ($fullName !== '') {
            $teacher = \App\Models\Teacher::whereRaw(
                "LOWER(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))) = ?",
                [$fullName]
            )->first();

            if ($teacher) {
                return $this->linkTeacherToUser($user, $teacher);
            }
        }

        return null;
    }

    private function linkTeacherToUser(\App\Models\User $user, \App\Models\Teacher $teacher): \App\Models\Teacher
    {
        $user->forceFill(['teacher_id' => $teacher->id])->save();

        return $teacher;
    }
}
