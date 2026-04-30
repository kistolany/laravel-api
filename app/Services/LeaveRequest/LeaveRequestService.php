<?php

namespace App\Services\LeaveRequest;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\LeaveRequest\LeaveRequestResource;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassStudent;
use App\Models\LeaveRequest;
use App\Models\Students;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\Concerns\ServiceTraceable;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeaveRequestService
{
    use ServiceTraceable;

    public function list(array $filters, ?Authenticatable $user): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function () use ($filters, $user): PaginatedResult {
            $query = $this->baseListQuery();

            $this->applyActorScope($query, $user);
            $this->applyFilters($query, $filters, $user);

            $perPage = max(1, min((int) ($filters['per_page'] ?? 10), 100));
            $paginator = $query->orderByDesc('leave_requests.created_at')->paginate($perPage);

            return PaginatedResult::fromPaginator($paginator, LeaveRequestResource::class);
        });
    }

    public function create(array $data, ?Authenticatable $user): LeaveRequest
    {
        return $this->trace(__FUNCTION__, function () use ($data, $user): LeaveRequest {
            $payload = $this->resolveStorePayload($data, $user);

            $leave = LeaveRequest::create($payload);
            $this->pushTeacherLeaveNotification($leave, $user);

            return $leave;
        });
    }

    public function updateStatus(int $id, array $data, ?Authenticatable $user): LeaveRequest
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data, $user): LeaveRequest {
            $leave = $this->findOrFail($id);
            $wasNotApproved = $leave->status !== 'approved';
            $isApproving = $data['status'] === 'approved';

            $leave->status = $data['status'];
            $leave->save();

            if ($wasNotApproved && $isApproving) {
                $this->generateAutoAttendance($leave);
            }

            $this->pushLeaveDecisionNotification($leave, $user);

            return $leave;
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id): void {
            $this->findOrFail($id)->delete();
        });
    }

    private function baseListQuery(): Builder
    {
        return LeaveRequest::query()
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
    }

    private function applyActorScope(Builder $query, ?Authenticatable $user): void
    {
        if ($user instanceof Teacher) {
            $query->where('leave_requests.requester_type', 'teacher')
                ->where('leave_requests.requester_id', $user->id);

            return;
        }

        if (! $user instanceof User) {
            return;
        }

        if ($user->hasRole('Student')) {
            $this->scopeStudentUser($query, $user);
            return;
        }

        if ($user->hasRole('Teacher')) {
            $this->scopeTeacherUser($query, $user);
        }
    }

    private function applyFilters(Builder $query, array $filters, ?Authenticatable $user): void
    {
        if (! empty($filters['requester_type'])) {
            $query->where('leave_requests.requester_type', $filters['requester_type']);
        }

        if (! empty($filters['role']) && $this->isAdminUser($user)) {
            $this->applyRoleFilter($query, $filters['role']);
        }

        if (! empty($filters['leave_type'])) {
            $query->where('leave_requests.leave_type', $filters['leave_type']);
        }

        if (! empty($filters['status'])) {
            $query->where('leave_requests.status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($query) use ($search) {
                $query->where('leave_requests.requester_name', 'like', "%{$search}%")
                    ->orWhere('leave_requests.requester_name_kh', 'like', "%{$search}%");
            });
        }
    }

    private function scopeStudentUser(Builder $query, User $user): void
    {
        $studentId = $user->student_id ?: Students::where('phone', $user->phone)->value('id');

        if ($studentId) {
            $query->where('leave_requests.requester_type', 'student')
                ->where('leave_requests.requester_id', $studentId);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function scopeTeacherUser(Builder $query, User $user): void
    {
        $teacher = $this->resolveTeacherForUser($user);

        if ($teacher) {
            $query->where('leave_requests.requester_type', 'teacher')
                ->where('leave_requests.requester_id', $teacher->id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function applyRoleFilter(Builder $query, string $role): void
    {
        if ($role === 'Student') {
            $query->where('leave_requests.requester_type', 'student');
            return;
        }

        $query->where('leave_requests.requester_type', 'teacher')
            ->whereIn('leave_requests.requester_id', function ($query) use ($role) {
                $query->select('id')->from('teachers')->where('role', $role);
            });
    }

    private function isAdminUser(?Authenticatable $user): bool
    {
        return $user instanceof User && ($user->hasRole('Admin') || $user->hasRole('Staff'));
    }

    private function resolveStorePayload(array $data, ?Authenticatable $user): array
    {
        $payload = $this->applyRequesterFromUser($data, $user);

        if (empty($payload['requester_type']) || empty($payload['requester_id'])) {
            throw new ApiException(ResponseStatus::BAD_REQUEST, 'Requester could not be resolved.');
        }

        $start = Carbon::parse($payload['start_date']);
        $end = Carbon::parse($payload['end_date']);

        $payload['days'] = $start->diffInDays($end) + 1;
        $payload['status'] = 'pending';

        return $payload;
    }

    private function applyRequesterFromUser(array $data, ?Authenticatable $user): array
    {
        if ($user instanceof Teacher) {
            return array_merge($data, [
                'requester_type' => 'teacher',
                'requester_id' => $user->id,
                'requester_name' => $user->full_name ?? trim($user->first_name . ' ' . $user->last_name),
            ]);
        }

        if ($user instanceof User && $user->hasRole('Student')) {
            return $this->applyRequesterFromStudentUser($data, $user);
        }

        if ($user instanceof User && $user->hasRole('Teacher')) {
            return $this->applyRequesterFromTeacherUser($data, $user);
        }

        return $data;
    }

    private function applyRequesterFromStudentUser(array $data, User $user): array
    {
        $student = $user->student ?: Students::where('phone', $user->phone)->first();

        if (! $student) {
            return $data;
        }

        return array_merge($data, [
            'requester_type' => 'student',
            'requester_id' => $student->id,
            'requester_name' => $student->full_name_en,
            'requester_name_kh' => $student->full_name_kh,
        ]);
    }

    private function applyRequesterFromTeacherUser(array $data, User $user): array
    {
        $teacher = $this->resolveTeacherForUser($user);

        if (! $teacher) {
            return $data;
        }

        return array_merge($data, [
            'requester_type' => 'teacher',
            'requester_id' => $teacher->id,
            'requester_name' => $teacher->full_name,
            'requester_name_kh' => $data['requester_name_kh'] ?? '',
        ]);
    }

    private function generateAutoAttendance(LeaveRequest $leave): void
    {
        if ($leave->requester_type === 'teacher') {
            $this->generateTeacherAttendance($leave);
            return;
        }

        if ($leave->requester_type === 'student') {
            $this->generateStudentAttendance($leave);
        }
    }

    private function generateTeacherAttendance(LeaveRequest $leave): void
    {
        $rows = [];

        foreach (CarbonPeriod::create($leave->start_date, $leave->end_date) as $date) {
            $rows[] = [
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

        TeacherAttendance::upsert($rows, ['teacher_id', 'attendance_date'], ['status', 'note', 'updated_at']);
    }

    private function generateStudentAttendance(LeaveRequest $leave): void
    {
        $classIds = ClassStudent::where('student_id', $leave->requester_id)
            ->where('status', 'Active')
            ->pluck('class_id');

        $sessions = AttendanceSession::whereIn('class_id', $classIds)
            ->whereBetween('session_date', [$leave->start_date, $leave->end_date])
            ->get();

        foreach ($sessions as $session) {
            AttendanceRecord::updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'student_id' => $leave->requester_id,
                ],
                [
                    'status' => 'Leave',
                    'note' => 'Auto-generated from Approved Leave Request (ID: ' . $leave->id . ')',
                ]
            );
        }
    }

    private function pushTeacherLeaveNotification(LeaveRequest $leave, ?Authenticatable $user): void
    {
        if ($leave->requester_type !== 'teacher') {
            return;
        }

        $name = trim((string) ($leave->requester_name ?: 'A teacher'));
        $type = trim((string) ($leave->leave_type ?: 'leave'));
        $dates = trim((string) $leave->start_date) . ' to ' . trim((string) $leave->end_date);

        DB::table('push_notifications')->insert([
            'title' => 'New Teacher Leave Request',
            'body' => "{$name} submitted a {$type} request for {$dates}.",
            'audience' => 'admin',
            'priority' => 'warning',
            'sent_by' => $user?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pushLeaveDecisionNotification(LeaveRequest $leave, ?Authenticatable $user): void
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
            'title' => "Leave Request {$statusLabel}",
            'body' => "Your {$leave->leave_type} request from {$startDate} to {$endDate} was {$status}.",
            'audience' => 'all',
            'priority' => $status === 'rejected' ? 'warning' : 'info',
            'sent_by' => $user?->id,
            'target_user_id' => $targetUserId,
            'created_at' => now(),
            'updated_at' => now(),
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

    private function resolveTeacherForUser(User $user): ?Teacher
    {
        if ($user->teacher_id) {
            return Teacher::find($user->teacher_id);
        }

        $teacher = $this->findTeacherByUsername($user)
            ?? $this->findTeacherByPhone($user)
            ?? $this->findTeacherByFullName($user);

        return $teacher ? $this->linkTeacherToUser($user, $teacher) : null;
    }

    private function findTeacherByUsername(User $user): ?Teacher
    {
        $username = trim((string) $user->username);

        if ($username === '') {
            return null;
        }

        return Teacher::where('username', $username)
            ->orWhere('email', $username)
            ->first();
    }

    private function findTeacherByPhone(User $user): ?Teacher
    {
        $phone = trim((string) $user->phone);

        if ($phone === '') {
            return null;
        }

        return Teacher::where('phone_number', $phone)->first();
    }

    private function findTeacherByFullName(User $user): ?Teacher
    {
        $fullName = strtolower(preg_replace('/\s+/', ' ', trim((string) $user->full_name)));

        if ($fullName === '') {
            return null;
        }

        return Teacher::whereRaw(
            "LOWER(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))) = ?",
            [$fullName]
        )->first();
    }

    private function linkTeacherToUser(User $user, Teacher $teacher): Teacher
    {
        $user->forceFill(['teacher_id' => $teacher->id])->save();

        return $teacher;
    }

    private function findOrFail(int $id): LeaveRequest
    {
        $leave = LeaveRequest::find($id);

        if (! $leave) {
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Leave request not found.');
        }

        return $leave;
    }
}
