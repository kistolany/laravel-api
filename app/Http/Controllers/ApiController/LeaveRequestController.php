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
use App\Traits\ApiResponseTrait;

class LeaveRequestController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeaveRequest::query()
            ->leftJoin('academic_info', function ($join) {
                $join->on('leave_requests.requester_id', '=', 'academic_info.student_id')
                    ->where('leave_requests.requester_type', '=', 'student');
            })
            ->leftJoin('majors', 'academic_info.major_id', '=', 'majors.id')
            ->select('leave_requests.*', 'majors.name as major_name', 'academic_info.stage as year');

        // 1. Apply Automatic Restrictions based on Role
        if ($user instanceof \App\Models\Teacher) {
            // Logged in via Teacher guard
            $query->where('requester_type', 'teacher')->where('requester_id', $user->id);
        } elseif ($user instanceof \App\Models\User) {
            // Logged in via User guard (Standard account)
            if ($user->hasRole('Student')) {
                // Students only see their own requests
                $studentId = $user->student_id;
                if (!$studentId) {
                    $studentId = \App\Models\Students::where('phone', $user->phone)->value('id');
                }
                
                if ($studentId) {
                    $query->where('requester_type', 'student')->where('requester_id', $studentId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->hasRole('Teacher')) {
                // Teachers logged in via User account
                if ($user->teacher_id) {
                    $query->where('requester_type', 'teacher')->where('requester_id', $user->teacher_id);
                }
            }
        }

        // 2. Apply Administrative Filters (Only for Admin/Staff)
        $isAdmin = ($user instanceof \App\Models\User && ($user->hasRole('Admin') || $user->hasRole('Staff')));

        if ($request->filled('requester_type')) {
            $query->where('requester_type', $request->requester_type);
        }

        if ($request->filled('role') && $isAdmin) {
            $role = $request->role;
            if ($role === 'Student') {
                $query->where('requester_type', 'student');
            } else {
                $query->where('requester_type', 'teacher')
                    ->whereIn('requester_id', function ($q) use ($role) {
                        $q->select('id')->from('teachers')->where('role', $role);
                    });
            }
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('requester_name', 'like', "%{$search}%")
                    ->orWhere('requester_name_kh', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 10);
        $paginated = $query->latest()->paginate($perPage);
        
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
}
