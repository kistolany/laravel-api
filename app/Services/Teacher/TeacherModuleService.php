<?php

namespace App\Services\Teacher;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Teacher\TeacherAttendanceHistoryResource;
use App\Http\Resources\Teacher\TeacherClassResource;
use App\Http\Resources\Teacher\TeacherStudentResource;
use App\Models\AttendanceSession;
use App\Models\Classes;
use App\Models\MajorSubject;
use App\Models\Students;
use App\Models\Teacher;
use Illuminate\Support\Facades\Log;
class TeacherModuleService extends BaseService
{
    public function students(Teacher $teacher): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function () use ($teacher): PaginatedResult {
            $query = Students::with(['academicInfo.major', 'academicInfo.shift'])
                ->whereHas('academicInfo', fn ($academic) => $academic->where('major_id', $teacher->major_id));
            
            $query->when(request('search'), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('full_name_en', 'like', "%{$search}%")
                        ->orWhere('full_name_kh', 'like', "%{$search}%")
                        ->orWhere('id_card_number', 'like', "%{$search}%");
                });
            });
            
            $query->when(request('class_id'), function ($q, $classId) {
                $q->whereHas('classes', fn ($classQuery) => $classQuery->where('classes.id', $classId));
            });
            
            $query->when(request('shift_id'), function ($q, $shiftId) {
                $q->whereHas('academicInfo', fn ($academic) => $academic->where('shift_id', $shiftId));
            });
            
            $query->when(request('batch_year'), function ($q, $batchYear) {
                $q->whereHas('academicInfo', fn ($academic) => $academic->where('batch_year', $batchYear));
            });
            
            return $this->paginateResponse($query->latest(), TeacherStudentResource::class);
            
            
        });
    }

    public function classes(Teacher $teacher): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function () use ($teacher): PaginatedResult {
            $query = Classes::with(['programs.major', 'programs.shift'])
                ->withCount('students');

            $query->when(request('is_active'), fn ($q, $active) => $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOL)));
            $query->when(request('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));

            if ($teacher->major_id) {
                $query->whereHas('programs', fn ($q) => $q->where('major_id', $teacher->major_id));
            }

            if ($academicYear = request('academic_year')) {
                $query->whereHas('programs', fn ($q) => $q->where('academic_year', $academicYear));
            }
            if ($yearLevel = request('year_level')) {
                $query->whereHas('programs', fn ($q) => $q->where('year_level', $yearLevel));
            }
            if ($semester = request('semester')) {
                $query->whereHas('programs', fn ($q) => $q->where('semester', $semester));
            }
            if ($shiftId = request('shift_id')) {
                $query->whereHas('programs', fn ($q) => $q->where('shift_id', $shiftId));
            }

            return $this->paginateResponse($query->latest(), TeacherClassResource::class);
            
            
        });
    }

    public function classStudents(Teacher $teacher, int $classId)
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $classId) {
            $class = $this->findTeacherClass($teacher, $classId, true);
            
            return $class->students;
            
            
        });
    }

    public function attendanceOptions(Teacher $teacher): array
    {
        return $this->trace(__FUNCTION__, function () use ($teacher): array {
            $classQuery = Classes::with(['programs.major', 'programs.shift'])
                ->orderBy('name');

            if ($teacher->major_id) {
                $classQuery->whereHas('programs', fn ($q) => $q->where('major_id', $teacher->major_id));
            }
            if ($teacher->subject_id) {
                $classQuery->whereHas('programs', function ($q) use ($teacher) {
                    $q->whereExists(function ($ms) use ($teacher) {
                        $ms->selectRaw('1')
                            ->from((new MajorSubject())->getTable())
                            ->whereColumn('major_subjects.major_id', 'class_programs.major_id')
                            ->where('major_subjects.subject_id', $teacher->subject_id)
                            ->whereColumn('major_subjects.year_level', 'class_programs.year_level')
                            ->whereColumn('major_subjects.semester', 'class_programs.semester');
                    });
                });
            }

            $classes = $classQuery->get();

            $programData = $classes->flatMap(fn ($c) => $c->programs);

            return [
                'major' => [
                    'id' => $teacher->major?->id,
                    'name_en' => $teacher->major?->name_eg,
                    'name_kh' => $teacher->major?->name_kh,
                ],
                'subject' => [
                    'id' => $teacher->subject?->id,
                    'code' => $teacher->subject?->subject_Code,
                    'name_en' => $teacher->subject?->name_eg,
                    'name_kh' => $teacher->subject?->name_kh,
                ],
                'year_levels' => $programData->pluck('year_level')->filter()->unique()->values(),
                'semesters' => $programData->pluck('semester')->filter()->unique()->values(),
                'academic_years' => $programData->pluck('academic_year')->filter()->unique()->values(),
                'classes' => TeacherClassResource::collection($classes)->resolve(),
            ];
            
            
        });
    }

    public function attendanceHistory(Teacher $teacher): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function () use ($teacher): PaginatedResult {
            $query = AttendanceSession::with([
                'classroom',
                'major',
                'subject',
            ])
                ->withCount([
                    'records as total_records',
                    'records as present_count' => fn ($records) => $records->whereIn('status', ['Present', 'present', 'P', 'p']),
                    'records as absent_count' => fn ($records) => $records->whereIn('status', ['Absent', 'absent', 'A', 'a']),
                    'records as late_count' => fn ($records) => $records->whereIn('status', ['Late', 'late', 'L', 'l']),
                    'records as excused_count' => fn ($records) => $records->whereIn('status', ['Excused', 'excused', 'Excuse', 'excuse', 'E', 'e']),
                ])
                ->where('teacher_id', $teacher->id);
            
            $query->when(request('class_id'), fn ($q, $classId) => $q->where('class_id', $classId));
            $query->when(request('session_date'), fn ($q, $date) => $q->whereDate('session_date', $date));
            $query->when(request('year_level'), fn ($q, $level) => $q->where('year_level', $level));
            $query->when(request('semester'), fn ($q, $semester) => $q->where('semester', $semester));
            
            return $this->paginateResponse(
                $query->orderByDesc('session_date')->orderByDesc('session_number')->orderByDesc('id'),
                TeacherAttendanceHistoryResource::class
            );
            
            
        });
    }

    public function findTeacherAttendanceSession(Teacher $teacher, int $sessionId): AttendanceSession
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $sessionId): AttendanceSession {
            $session = AttendanceSession::where('teacher_id', $teacher->id)->find($sessionId);
            
            if (!$session) {
                Log::warning('Teacher attendance session not found.', [
                    'teacher_id' => $teacher->id,
                    'session_id' => $sessionId,
                ]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Attendance session not found.');
            }
            
            return $session;
            
            
        });
    }

    public function findTeacherClass(Teacher $teacher, int $classId, bool $withStudents = false): Classes
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $classId, $withStudents): Classes {
            $query = Classes::query()->where('major_id', $teacher->major_id);
            
            if ($withStudents) {
                $query->with(['students.academicInfo.major', 'students.academicInfo.shift']);
            }
            
            $class = $query->find($classId);
            
            if (!$class) {
                Log::warning('Teacher class not found.', [
                    'teacher_id' => $teacher->id,
                    'class_id' => $classId,
                ]);
                throw new ApiException(ResponseStatus::NOT_FOUND, 'Class not found for this teacher.');
            }
            
            return $class;
            
            
        });
    }
}




