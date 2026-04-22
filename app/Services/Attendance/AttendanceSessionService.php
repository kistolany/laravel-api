<?php

namespace App\Services\Attendance;

use App\DTOs\AttendanceRecordBulkResult;
use App\DTOs\AttendanceSessionCreateData;
use App\DTOs\AttendanceSessionDetail;
use App\Http\Resources\Attendance\AttendanceRecordBulkResource;
use App\Http\Resources\Attendance\AttendanceSessionCreateResource;
use App\Http\Resources\Attendance\AttendanceSessionDetailResource;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classes;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Shift;
use App\Models\Students;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\Concerns\ServiceTraceable;
use Illuminate\Support\Facades\DB;

class AttendanceSessionService
{
    use ServiceTraceable;

    private const DEFAULT_MATRIX_SESSION_COUNT = 30;
    private const MAX_MATRIX_SESSION_COUNT = 60;

    public function buildListResponse(): array
    {
        return $this->trace(__FUNCTION__, function (): array {
            $sessions = AttendanceSession::with(['classroom', 'subject', 'major', 'shift'])
                ->orderByDesc('session_date')
                ->orderByDesc('session_number')
                ->orderByDesc('id')
                ->get()
                ->map(fn (AttendanceSession $s) => [
                    'id'             => $s->id,
                    'class_id'       => $s->class_id,
                    'class_name'     => $s->classroom?->name ?? $s->classroom?->code ?? '—',
                    'subject_id'     => $s->subject_id,
                    'subject_name'   => $s->subject?->name_eg ?? $s->subject?->name_kh ?? '—',
                    'session_date'   => $this->formatDate($s->session_date),
                    'session_number' => (int) $s->session_number,
                    'major_name'     => $s->major?->name_eg ?? $s->major?->name_kh ?? '—',
                    'shift_name'     => $s->shift?->name ?? '—',
                    'created_at'     => $s->created_at?->toIso8601String(),
                ])
                ->all();

            return $this->successResponse(200, 'Attendance sessions retrieved successfully.', $sessions);
        });
    }

    public function buildDetailResponse(int $id): array
    {
        return $this->trace(__FUNCTION__, function () use ($id): array {
            $detail = $this->getDetail($id);
            
            if (!$detail) {
                return $this->errorResponse(404, 'Attendance session not found.');
            }
            
            return $this->successResponse(
                200,
                'Attendance session retrieved successfully.',
                (new AttendanceSessionDetailResource($detail))->toArray(request())
            );
            
            
        });
    }

    public function buildTeacherDetailResponse(Teacher $teacher, int $id): array
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $id): array {
            $session = $this->detailQuery()
                ->where('teacher_id', $teacher->id)
                ->find($id);
            
            if (!$session) {
                return $this->errorResponse(404, 'Attendance session not found.');
            }
            
            return $this->successResponse(
                200,
                'Attendance session retrieved successfully.',
                (new AttendanceSessionDetailResource($this->mapDetail($session)))->toArray(request())
            );
            
            
        });
    }

    public function buildMajorDetailResponse(int $majorId): array
    {
        return $this->trace(__FUNCTION__, function () use ($majorId): array {
            $sessions = $this->getSessionsByMajorId($majorId);
            $rows = [];
            $rowNumber = 1;
            $summary = [
                'total_records' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
            ];
            
            foreach ($sessions as $session) {
                $subjectName = $session->subject?->name_eg ?? $session->subject?->name_kh ?? '';
                $attendanceDate = $this->formatDisplayDate($session->session_date);
            
                foreach ($session->records as $record) {
                    $status = $this->normalizeStatus($record->status);
                    $summary['total_records']++;
            
                    switch ($status) {
                        case 'Present':
                            $summary['present']++;
                            break;
                        case 'Late':
                            $summary['late']++;
                            break;
                        case 'Excused':
                            $summary['excused']++;
                            break;
                        default:
                            $summary['absent']++;
                            break;
                    }
            
                    $rows[] = [
                        'no' => $rowNumber++,
                        'student_code' => $this->formatStudentCode((int) $record->student_id),
                        'student_name' => $record->student?->full_name_en ?? '',
                        'gender' => $record->student?->gender ?? '',
                        'subject' => $subjectName,
                        'attendance_date' => $attendanceDate,
                        'status' => $status,
                    ];
                }
            }
            
            $firstSession = $sessions->first();
            $major = $firstSession?->major ?? $firstSession?->classroom?->major;
            
            return $this->successResponse(
                200,
                'Attendance records retrieved successfully.',
                [
                    'major' => [
                        'id' => $major?->id,
                        'name_en' => $major?->name_eg,
                        'name_kh' => $major?->name_kh,
                    ],
                    'records' => $rows,
                    'summary' => $summary,
                ]
            );
            
            
        });
    }

    public function buildMajorSubjectReportResponse(int $majorId, int $subjectId): array
    {
        return $this->trace(__FUNCTION__, function () use ($majorId, $subjectId): array {
            $session = $this->getLatestSessionByMajorAndSubject($majorId, $subjectId);
            
            if (!$session) {
                return $this->errorResponse(404, 'Attendance report not found for the selected major and subject.');
            }
            
            $classroom = $session->classroom;
            
            if (!$classroom) {
                return $this->errorResponse(404, 'Class not found for this attendance report.');
            }
            
            $records = $session->records->keyBy('student_id');
            $summary = [
                'total_students' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'not_marked' => 0,
            ];
            
            $students = $classroom->students
                ->sortBy('full_name_en')
                ->values()
                ->map(function (Students $student) use ($records, &$summary) {
                    $record = $records->get($student->id);
                    $status = $record ? $this->normalizeStatus($record->status) : null;
            
                    $summary['total_students']++;
            
                    switch ($status) {
                        case 'Present':
                            $summary['present']++;
                            break;
                        case 'Late':
                            $summary['late']++;
                            break;
                        case 'Excused':
                            $summary['excused']++;
                            break;
                        case 'Absent':
                            $summary['absent']++;
                            break;
                        default:
                            $summary['not_marked']++;
                            break;
                    }
            
                    return [
                        'student_id' => (string) $student->id,
                        'full_name_en' => $student->full_name_en,
                        'full_name_kh' => $student->full_name_kh,
                        'status' => $status,
                        'present' => $status === 'Present',
                        'absent' => $status === 'Absent',
                        'late' => $status === 'Late',
                        'excused' => $status === 'Excused',
                    ];
                })
                ->all();
            
            $major = $session->major ?? $classroom->major;
            $subject = $session->subject;
            $shift = $session->shift ?? $classroom->shift;
            
            return $this->successResponse(
                200,
                'Attendance report retrieved successfully.',
                [
                    'session' => [
                        'id' => $session->id,
                        'session_date' => $this->formatDate($session->session_date),
                        'session_number' => (int) $session->session_number,
                        'academic_year' => $session->academic_year ?? $classroom->academic_year,
                        'year_level' => $session->year_level ?? $classroom->year_level,
                        'semester' => $session->semester ?? $classroom->semester,
                    ],
                    'class' => [
                        'id' => $classroom->id,
                        'code' => $classroom->code,
                        'section' => $classroom->section,
                    ],
                    'major' => [
                        'id' => $major?->id,
                        'name_en' => $major?->name_eg,
                        'name_kh' => $major?->name_kh,
                    ],
                    'subject' => [
                        'id' => $subject?->id,
                        'code' => $subject?->subject_Code,
                        'name_en' => $subject?->name_eg,
                        'name_kh' => $subject?->name_kh,
                    ],
                    'shift' => [
                        'id' => $shift?->id,
                        'name' => $shift?->name,
                        'time_range' => $shift?->time_range,
                    ],
                    'students' => $students,
                    'summary' => $summary,
                ]
            );
            
            
        });
    }

    public function buildMatrixResponse(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $classId = $this->toNullableInt($filters['class_id'] ?? null);
            $subjectId = $this->toNullableInt($filters['subject_id'] ?? null);

            if (!$classId || !$subjectId) {
                return $this->errorResponse(422, 'Validation failed.', [
                    'errors' => [
                        'class_id' => ['Class is required.'],
                        'subject_id' => ['Subject is required.'],
                    ],
                ]);
            }

            $class = $this->findClassForMatrix($classId, $filters);
            if (!$class) {
                return $this->errorResponse(404, 'Class not found.');
            }

            $subject = Subject::find($subjectId);
            if (!$subject) {
                return $this->errorResponse(404, 'Subject not found.');
            }

            if (!$this->subjectAllowedForMatrix($class, $subjectId, $filters)) {
                return $this->errorResponse(422, 'Validation failed.', [
                    'errors' => [
                        'subject_id' => ['Subject is not assigned to the selected class filters.'],
                    ],
                ]);
            }

            return $this->successResponse(
                200,
                'Attendance matrix retrieved successfully.',
                $this->buildMatrixPayload(
                    $class,
                    $subject,
                    $this->formatDate($filters['session_date'] ?? now()->toDateString()),
                    $this->matrixSessionCount($filters['session_count'] ?? null),
                    $filters
                )
            );
        });
    }

    public function saveMatrixResponse(array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($data): array {
            $class = $this->findClassForMatrix((int) $data['class_id'], $data);
            if (!$class) {
                return $this->errorResponse(404, 'Class not found.');
            }

            $subject = Subject::find((int) $data['subject_id']);
            if (!$subject) {
                return $this->errorResponse(404, 'Subject not found.');
            }

            if (!$this->subjectAllowedForMatrix($class, (int) $data['subject_id'], $data)) {
                return $this->errorResponse(422, 'Validation failed.', [
                    'errors' => [
                        'subject_id' => ['Subject is not assigned to the selected class filters.'],
                    ],
                ]);
            }

            $sessionDate = $this->formatDate($data['session_date']);
            $sessionCount = $this->matrixSessionCount($data['session_count'] ?? null);
            $validStudentIds = $class->students->pluck('id')->map(fn ($id) => (int) $id)->all();
            $validSet = array_fill_keys($validStudentIds, true);
            $resolvedStudentIds = $this->resolveStudentIdsFromRecords($data['records']);
            $validRecords = [];
            $errors = [];

            foreach ($data['records'] as $index => $record) {
                $resolvedId = $resolvedStudentIds[$index] ?? null;

                if (!$resolvedId) {
                    $errors["records.{$index}.student_id"][] = 'Invalid student_id format.';
                    continue;
                }

                if (!isset($validSet[$resolvedId])) {
                    $errors["records.{$index}.student_id"][] = 'Student does not belong to this class.';
                    continue;
                }

                $validRecords[] = [
                    'student_id' => $resolvedId,
                    'attendance' => array_slice($record['attendance'] ?? [], 0, $sessionCount),
                ];
            }

            if (!empty($errors)) {
                return $this->errorResponse(422, 'Validation failed.', ['errors' => $errors]);
            }

            $savedRecords = 0;

            $context = $this->resolveClassAttendanceContext($class, $data);

            DB::transaction(function () use ($class, $subject, $sessionDate, $sessionCount, $validRecords, $context, &$savedRecords): void {
                $sessions = [];

                for ($sessionNumber = 1; $sessionNumber <= $sessionCount; $sessionNumber++) {
                    $session = AttendanceSession::firstOrCreate(
                        [
                            'class_id' => $class->id,
                            'subject_id' => $subject->id,
                            'session_date' => $sessionDate,
                            'session_number' => $sessionNumber,
                        ],
                        [
                            'teacher_id' => null,
                            'major_id' => $context['major_id'],
                            'shift_id' => $context['shift_id'],
                            'academic_year' => $context['academic_year'],
                            'year_level' => $context['year_level'],
                            'semester' => $context['semester'],
                        ]
                    );

                    // Auto-inject Leave records for new matrix sessions
                    if ($session->wasRecentlyCreated) {
                        $leaveStudents = \App\Models\LeaveRequest::where('requester_type', 'student')
                            ->where('status', 'approved')
                            ->where('start_date', '<=', $sessionDate)
                            ->where('end_date', '>=', $sessionDate)
                            ->whereIn('requester_id', $class->students->pluck('id'))
                            ->get();
                            
                        foreach ($leaveStudents as $leave) {
                            \App\Models\AttendanceRecord::updateOrCreate(
                                ['attendance_session_id' => $session->id, 'student_id' => $leave->requester_id],
                                ['status' => 'Leave']
                            );
                        }
                    }
                    
                    $sessions[$sessionNumber] = $session;
                }

                $now = now();
                $rows = [];

                foreach ($validRecords as $record) {
                    for ($slotIndex = 0; $slotIndex < $sessionCount; $slotIndex++) {
                        $session = $sessions[$slotIndex + 1];
                        $rows[] = [
                            'attendance_session_id' => $session->id,
                            'student_id' => $record['student_id'],
                            'status' => $this->matrixStatusToBackend($record['attendance'][$slotIndex] ?? 'Att'),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    AttendanceRecord::upsert(
                        $rows,
                        ['attendance_session_id', 'student_id'],
                        ['status', 'updated_at']
                    );
                }

                $savedRecords = count($rows);
            });

            $class = $this->findClassForMatrix((int) $data['class_id'], $data) ?? $class;

            return $this->successResponse(
                200,
                'Attendance matrix saved successfully.',
                array_merge(
                    $this->buildMatrixPayload($class, $subject, $sessionDate, $sessionCount, $data),
                    ['saved_records' => $savedRecords]
                )
            );
        });
    }

    public function createSessionResponse(array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($data): array {
            $class = Classes::find($data['class_id']);
            
            if (!$class) {
                return $this->errorResponse(404, 'Class not found.');
            }
            
            return $this->persistSession($class, (int) $data['subject_id'], $data['session_date'], (int) $data['session_number']);
            
            
        });
    }

    public function createTeacherSessionResponse(Teacher $teacher, array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $data): array {
            $class = Classes::find($data['class_id']);
            
            if (!$class) {
                return $this->errorResponse(404, 'Class not found.');
            }
            
            if ((int) $class->major_id !== (int) $teacher->major_id) {
                return $this->errorResponse(403, 'Forbidden.');
            }
            
            if ((int) $data['subject_id'] !== (int) $teacher->subject_id) {
                return $this->errorResponse(403, 'Forbidden.');
            }
            
            $subjectAllowed = MajorSubject::query()
                ->where('major_id', $class->major_id)
                ->where('subject_id', $teacher->subject_id)
                ->where('year_level', $class->year_level)
                ->where('semester', $class->semester)
                ->exists();
            
            if (!$subjectAllowed) {
                return $this->errorResponse(422, 'Validation failed.', [
                    'errors' => [
                        'subject_id' => ['Subject is not assigned to this class major/year/semester.'],
                    ],
                ]);
            }
            
            return $this->persistSession(
                $class,
                (int) $data['subject_id'],
                $data['session_date'],
                (int) $data['session_number'],
                $teacher->id
            );
            
            
        });
    }

    public function recordAttendanceResponse(int $sessionId, array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($sessionId, $data): array {
            $session = AttendanceSession::with('classroom.students')->find($sessionId);
            
            if (!$session) {
                return $this->errorResponse(404, 'Attendance session not found.');
            }
            
            return $this->recordAttendanceForSession($session, $data);
            
            
        });
    }

    public function recordTeacherAttendanceResponse(Teacher $teacher, int $sessionId, array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($teacher, $sessionId, $data): array {
            $session = AttendanceSession::with('classroom.students')
                ->where('teacher_id', $teacher->id)
                ->find($sessionId);
            
            if (!$session) {
                return $this->errorResponse(404, 'Attendance session not found.');
            }
            
            if ((int) $session->major_id !== (int) $teacher->major_id || (int) $session->subject_id !== (int) $teacher->subject_id) {
                return $this->errorResponse(403, 'Forbidden.');
            }
            
            return $this->recordAttendanceForSession($session, $data);
            
            
        });
    }

    private function recordAttendanceForSession(AttendanceSession $session, array $data): array
    {
        if ((int) $data['subject_id'] !== (int) $session->subject_id) {
            return $this->errorResponse(422, 'Validation failed.', [
                'errors' => [
                    'subject_id' => ['Subject does not match attendance session.'],
                ],
            ]);
        }

        $sessionDate = $this->formatDate($session->session_date);
        $payloadDate = $this->formatDate($data['session_date']);

        if ($payloadDate !== '' && $sessionDate !== '' && $payloadDate !== $sessionDate) {
            return $this->errorResponse(422, 'Validation failed.', [
                'errors' => [
                    'session_date' => ['Session date does not match attendance session.'],
                ],
            ]);
        }

        $classroom = $session->classroom;

        if (!$classroom) {
            return $this->errorResponse(404, 'Class not found for this session.');
        }

        $validStudentIds = $classroom->students->pluck('id')->all();
        $validSet = array_fill_keys($validStudentIds, true);
        $resolvedStudentIds = $this->resolveStudentIdsFromRecords($data['records']);
        $now = now();
        $rows = [];
        $errors = [];

        foreach ($data['records'] as $index => $record) {
            $resolvedId = $resolvedStudentIds[$index] ?? null;

            if (!$resolvedId) {
                $errors["records.{$index}.student_id"][] = 'Invalid student_id format.';
                continue;
            }

            if (!isset($validSet[$resolvedId])) {
                $errors["records.{$index}.student_id"][] = 'Student does not belong to this class.';
                continue;
            }

            $rows[$resolvedId] = [
                'attendance_session_id' => $session->id,
                'student_id' => $resolvedId,
                'status' => $this->normalizeStatus($record['status']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($errors)) {
            return $this->errorResponse(422, 'Validation failed.', ['errors' => $errors]);
        }

        AttendanceRecord::upsert(
            array_values($rows),
            ['attendance_session_id', 'student_id'],
            ['status', 'updated_at']
        );

        $dto = new AttendanceRecordBulkResult($session->id, count($rows));

        return $this->successResponse(
            200,
            'Attendance recorded successfully.',
            (new AttendanceRecordBulkResource($dto))->toArray(request())
        );
    }

    private function persistSession(
        Classes $class,
        int $subjectId,
        mixed $sessionDate,
        int $sessionNumber,
        ?int $teacherId = null
    ): array {
        $duplicate = AttendanceSession::query()
            ->where('class_id', $class->id)
            ->where('subject_id', $subjectId)
            ->where('session_date', $sessionDate)
            ->where('session_number', $sessionNumber)
            ->exists();

        if ($duplicate) {
            return $this->errorResponse(
                422,
                'Attendance session already exists.',
                ['errors' => ['session' => ['Attendance session already exists.']]]
            );
        }

        $session = AttendanceSession::create([
            'teacher_id' => $teacherId,
            'class_id' => $class->id,
            'subject_id' => $subjectId,
            'session_date' => $sessionDate,
            'session_number' => $sessionNumber,
            'major_id' => $class->major_id,
            'shift_id' => $class->shift_id,
            'academic_year' => $class->academic_year,
            'year_level' => $class->year_level,
            'semester' => $class->semester,
        ]);

        $dto = new AttendanceSessionCreateData(
            $session->id,
            $session->class_id,
            $session->subject_id,
            $this->formatDate($session->session_date),
            (int) $session->session_number
        );

        return $this->successResponse(
            201,
            'Attendance session created successfully.',
            (new AttendanceSessionCreateResource($dto))->toArray(request())
        );
    }

    public function getDetail(int $id): ?AttendanceSessionDetail
    {
        return $this->trace(__FUNCTION__, function () use ($id): ?AttendanceSessionDetail {
            $session = $this->detailQuery()->find($id);
            
            if (!$session) {
                return null;
            }
            
            return $this->mapDetail($session);
            
            
        });
    }

    public function getDetailsByMajorId(int $majorId): array
    {
        return $this->trace(__FUNCTION__, function () use ($majorId): array {
            return $this->getSessionsByMajorId($majorId)
                ->map(fn (AttendanceSession $session) => $this->mapDetail($session))
                ->all();
            
            
        });
    }

    public function getSessionsByMajorId(int $majorId)
    {
        return $this->trace(__FUNCTION__, function () use ($majorId) {
            return $this->detailQuery()
                ->where(function ($query) use ($majorId) {
                    $query->where('major_id', $majorId)
                        ->orWhere(function ($query) use ($majorId) {
                            $query->whereNull('major_id')
                                ->whereHas('classroom', fn ($classQuery) => $classQuery->where('major_id', $majorId));
                        });
                })
                ->orderByDesc('session_date')
                ->orderByDesc('session_number')
                ->orderByDesc('id')
                ->get();
            
            
        });
    }

    public function getLatestSessionByMajorAndSubject(int $majorId, int $subjectId): ?AttendanceSession
    {
        return $this->trace(__FUNCTION__, function () use ($majorId, $subjectId): ?AttendanceSession {
            return $this->detailQuery()
                ->with('classroom.students')
                ->where('subject_id', $subjectId)
                ->where(function ($query) use ($majorId) {
                    $query->where('major_id', $majorId)
                        ->orWhere(function ($query) use ($majorId) {
                            $query->whereNull('major_id')
                                ->whereHas('classroom', fn ($classQuery) => $classQuery->where('major_id', $majorId));
                        });
                })
                ->orderByDesc('session_date')
                ->orderByDesc('session_number')
                ->orderByDesc('id')
                ->first();
            
            
        });
    }

    private function mapDetail(AttendanceSession $session): AttendanceSessionDetail
    {
        $summary = [
            'total_students' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
        ];

        $students = [];

        foreach ($session->records as $record) {
            $status = $this->normalizeStatus($record->status);

            $students[] = [
                'student_id' => (string) $record->student_id,
                'full_name_en' => $record->student?->full_name_en ?? '',
                'status' => $status,
            ];

            $summary['total_students']++;

            switch ($status) {
                case 'Present':
                    $summary['present']++;
                    break;
                case 'Late':
                    $summary['late']++;
                    break;
                case 'Excused':
                    $summary['excused']++;
                    break;
                default:
                    $summary['absent']++;
            }
        }

        $summary['attendance_rate'] = $summary['total_students'] > 0
            ? round(($summary['present'] / $summary['total_students']) * 100, 2)
            : 0;

        return new AttendanceSessionDetail($session, $students, $summary);
    }

    private function detailQuery()
    {
        return AttendanceSession::with([
            'classroom.major',
            'classroom.shift',
            'major',
            'subject',
            'shift',
            'records.student',
        ]);
    }

    private function buildMatrixPayload(Classes $class, Subject $subject, string $sessionDate, int $sessionCount, array $filters = []): array
    {
        $studentIds = $class->students
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $sessions = AttendanceSession::query()
            ->with(['records' => function ($query) use ($studentIds) {
                empty($studentIds)
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('student_id', $studentIds);
            }])
            ->where('class_id', $class->id)
            ->where('subject_id', $subject->id)
            ->where('session_date', $sessionDate)
            ->whereBetween('session_number', [1, $sessionCount])
            ->get()
            ->keyBy(fn (AttendanceSession $session) => (int) $session->session_number);

        $recordMap = [];
        foreach ($sessions as $sessionNumber => $session) {
            foreach ($session->records as $record) {
                $recordMap[(int) $sessionNumber][(int) $record->student_id] = $this->statusToUi($record->status);
            }
        }

        // Fetch all approved leaves for this date for students in this class
        $approvedLeaves = \App\Models\LeaveRequest::where('requester_type', 'student')
            ->where('status', 'approved')
            ->where('start_date', '<=', $sessionDate)
            ->where('end_date', '>=', $sessionDate)
            ->whereIn('requester_id', $studentIds)
            ->pluck('requester_id')
            ->toArray();
        $leaveSet = array_flip($approvedLeaves);

        $summary = [
            'total_students' => 0,
            'total_sessions' => $sessionCount,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'excused' => 0,
        ];

        $students = $class->students
            ->filter(fn (Students $student) => $this->studentMatchesMatrixFilters($student, $filters))
            ->sortBy(fn (Students $student) => $student->full_name_en ?: $student->full_name_kh ?: $student->id)
            ->values()
            ->map(function (Students $student, int $index) use ($sessionCount, $recordMap, &$summary): array {
                $attendance = [];

                for ($sessionNumber = 1; $sessionNumber <= $sessionCount; $sessionNumber++) {
                    $status = $recordMap[$sessionNumber][$student->id] ?? (isset($leaveSet[$student->id]) ? 'P' : 'Att');
                    $attendance[] = $status;

                    match ($status) {
                        'A' => $summary['absent']++,
                        'L' => $summary['late']++,
                        'P' => $summary['excused']++,
                        default => $summary['present']++,
                    };
                }

                $summary['total_students']++;

                return [
                    'key' => (string) $student->id,
                    'no' => $index + 1,
                    'student_id' => $student->id,
                    'student_code' => $student->id_card_number ?: $student->barcode,
                    'full_name_kh' => $student->full_name_kh,
                    'full_name_en' => $student->full_name_en,
                    'gender' => $student->gender,
                    'dob' => $this->formatDate($student->dob),
                    'batch_year' => $student->academicInfo?->batch_year,
                    'stage' => $student->academicInfo?->stage,
                    'major_name' => $student->academicInfo?->major?->name,
                    'attendance' => $attendance,
                ];
            })
            ->all();

        $context = $this->resolveClassAttendanceContext($class, $filters);

        return [
            'session_date' => $sessionDate,
            'session_count' => $sessionCount,
            'class' => [
                'id' => $class->id,
                'name' => $class->name ?? $class->code,
                'academic_year' => $context['academic_year'],
                'year_level' => $context['year_level'],
                'semester' => $context['semester'],
            ],
            'major' => [
                'id' => $context['major']?->id,
                'name' => $context['major']?->name,
            ],
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'label' => $subject->name,
            ],
            'shift' => [
                'id' => $context['shift']?->id,
                'name' => $context['shift']?->name,
                'time_range' => $context['shift']?->time_range,
            ],
            'saved_sessions' => $sessions->count(),
            'has_saved_attendance' => $sessions->isNotEmpty(),
            'students' => $students,
            'summary' => $summary,
        ];
    }

    private function findClassForMatrix(int $classId, array $filters = []): ?Classes
    {
        $query = Classes::query()
            ->with(['major', 'shift', 'programs.major', 'programs.shift'])
            ->with(['students' => function ($query) use ($filters) {
                $query
                    ->wherePivot('status', 'Active')
                    ->with('academicInfo.major.faculty');

                $this->applyStudentMatrixFilters($query, $filters);
            }]);

        $this->applyClassMatrixFilters($query, $filters);

        return $query->find($classId);
    }

    private function applyClassMatrixFilters($query, array $filters): void
    {
        $academicYear = $this->toNullableString($filters['academic_year'] ?? null);
        $majorId = $this->toNullableInt($filters['major_id'] ?? null);
        $shiftId = $this->toNullableInt($filters['shift_id'] ?? null);
        $yearLevel = $this->toNullableYearLevel($filters['year_level'] ?? $filters['stage'] ?? null);
        $semester = $this->toNullableInt($filters['semester'] ?? null);

        $query->when($academicYear, fn ($q, $value) => $q->where('academic_year', $value));

        if (!$majorId && !$shiftId && !$yearLevel && !$semester) {
            return;
        }

        $query->where(function ($context) use ($majorId, $shiftId, $yearLevel, $semester) {
            $context
                ->where(function ($direct) use ($majorId, $shiftId, $yearLevel, $semester) {
                    $direct
                        ->when($majorId, fn ($q, $value) => $q->where('major_id', $value))
                        ->when($shiftId, fn ($q, $value) => $q->where('shift_id', $value))
                        ->when($yearLevel, fn ($q, $value) => $q->where('year_level', $value))
                        ->when($semester, fn ($q, $value) => $q->where('semester', $value));
                })
                ->orWhereHas('programs', function ($program) use ($majorId, $shiftId, $yearLevel, $semester) {
                    $program
                        ->when($majorId, fn ($q, $value) => $q->where('major_id', $value))
                        ->when($shiftId, fn ($q, $value) => $q->where('shift_id', $value))
                        ->when($yearLevel, fn ($q, $value) => $q->where('year_level', $value))
                        ->when($semester, fn ($q, $value) => $q->where('semester', $value));
                });
        });
    }

    private function applyStudentMatrixFilters($query, array $filters): void
    {
        $majorId = $this->toNullableInt($filters['major_id'] ?? null);
        $shiftId = $this->toNullableInt($filters['shift_id'] ?? null);
        $facultyId = $this->toNullableInt($filters['faculty_id'] ?? null);
        $yearLevel = $this->toNullableYearLevel($filters['year_level'] ?? $filters['stage'] ?? null);
        $batchYear = $this->toNullableString($filters['batch_year'] ?? $filters['batch'] ?? null);
        $studyDay = $this->toNullableString($filters['study_day'] ?? $filters['study_days'] ?? null);

        $studentName = $this->toNullableString($filters['student_name'] ?? $filters['search'] ?? null);

        if ($studentName) {
            $query->where(function ($q) use ($studentName) {
                $q->where('full_name_en', 'like', "%{$studentName}%")
                    ->orWhere('full_name_kh', 'like', "%{$studentName}%")
                    ->orWhere('id_card_number', 'like', "%{$studentName}%");
            });
        }

        if (!$majorId && !$shiftId && !$facultyId && !$yearLevel && !$batchYear && !$studyDay) {
            return;
        }

        $query->whereHas('academicInfo', function ($academic) use ($majorId, $shiftId, $facultyId, $yearLevel, $batchYear, $studyDay) {
            $academic
                ->when($majorId, fn ($q, $value) => $q->where('major_id', $value))
                ->when($shiftId, fn ($q, $value) => $q->where('shift_id', $value))
                ->when($batchYear, fn ($q, $value) => $q->where('batch_year', $value))
                ->when($studyDay, fn ($q, $value) => $q->where('study_days', $value));

            if ($facultyId) {
                $academic->whereHas('major', fn ($major) => $major->where('faculty_id', $facultyId));
            }

            if ($yearLevel) {
                $academic->where(function ($stage) use ($yearLevel) {
                    $stage
                        ->where('stage', (string) $yearLevel)
                        ->orWhere('stage', 'Year ' . $yearLevel);
                });
            }
        });
    }

    private function subjectAllowedForMatrix(Classes $class, int $subjectId, array $filters): bool
    {
        $contexts = $this->classSubjectContexts($class, $filters);

        if (empty($contexts)) {
            return true;
        }

        $curriculumQuery = MajorSubject::query()
            ->where(function ($query) use ($contexts) {
                foreach ($contexts as $context) {
                    $query->orWhere(function ($slot) use ($context) {
                        $this->applyMajorSubjectContext($slot, $context);
                    });
                }
            });

        if (!(clone $curriculumQuery)->exists()) {
            return true;
        }

        return $curriculumQuery
            ->where('subject_id', $subjectId)
            ->exists();
    }

    private function classSubjectContexts(Classes $class, array $filters): array
    {
        $contexts = $class->programs
            ->map(fn ($program) => [
                'major_id' => $program->major_id,
                'year_level' => $program->year_level,
                'semester' => $program->semester,
            ])
            ->filter(fn (array $context) => $context['major_id'])
            ->values()
            ->all();

        if (empty($contexts) && $class->major_id) {
            $contexts[] = [
                'major_id' => $class->major_id,
                'year_level' => $class->year_level,
                'semester' => $class->semester,
            ];
        }

        return array_values(array_filter(array_map(
            fn (array $context) => $this->mergeRequestedSubjectContext($context, $filters),
            $contexts
        )));
    }

    private function mergeRequestedSubjectContext(array $context, array $filters): ?array
    {
        $requested = [
            'major_id' => $this->toNullableInt($filters['major_id'] ?? null),
            'year_level' => $this->toNullableYearLevel($filters['year_level'] ?? $filters['stage'] ?? null),
            'semester' => $this->toNullableInt($filters['semester'] ?? null),
        ];

        foreach ($requested as $key => $value) {
            if (!$value) {
                continue;
            }

            if (!empty($context[$key]) && (int) $context[$key] !== $value) {
                return null;
            }

            $context[$key] = $value;
        }

        return $context;
    }

    private function applyMajorSubjectContext($query, array $context): void
    {
        $query
            ->when($context['major_id'] ?? null, fn ($q, $value) => $q->where('major_id', $value))
            ->when($context['year_level'] ?? null, fn ($q, $value) => $q->where('year_level', $value))
            ->when($context['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value));
    }

    private function resolveClassAttendanceContext(Classes $class, array $filters): array
    {
        $majorId = $this->toNullableInt($filters['major_id'] ?? null);
        $shiftId = $this->toNullableInt($filters['shift_id'] ?? null);
        $yearLevel = $this->toNullableYearLevel($filters['year_level'] ?? $filters['stage'] ?? null);
        $semester = $this->toNullableInt($filters['semester'] ?? null);
        $academicYear = $this->toNullableString($filters['academic_year'] ?? null) ?? $class->academic_year;

        $program = $class->programs->first(function ($program) use ($majorId, $shiftId, $yearLevel, $semester) {
            if ($majorId && (int) ($program->major_id ?? 0) !== $majorId) {
                return false;
            }

            if ($shiftId && (int) ($program->shift_id ?? 0) !== $shiftId) {
                return false;
            }

            if ($yearLevel && (int) ($program->year_level ?? 0) !== $yearLevel) {
                return false;
            }

            if ($semester && (int) ($program->semester ?? 0) !== $semester) {
                return false;
            }

            return true;
        });

        $resolvedMajorId = $majorId ?: ($program?->major_id ?: $class->major_id);
        $resolvedShiftId = $shiftId ?: ($program?->shift_id ?: $class->shift_id);
        $resolvedYearLevel = $yearLevel ?: ($program?->year_level ?: $class->year_level);
        $resolvedSemester = $semester ?: ($program?->semester ?: $class->semester);

        return [
            'major_id' => $resolvedMajorId,
            'shift_id' => $resolvedShiftId,
            'academic_year' => $academicYear,
            'year_level' => $resolvedYearLevel,
            'semester' => $resolvedSemester,
            'major' => $this->resolveMajorModel($class, $resolvedMajorId),
            'shift' => $this->resolveShiftModel($class, $resolvedShiftId),
        ];
    }

    private function resolveMajorModel(Classes $class, mixed $majorId): ?Major
    {
        $majorId = $this->toNullableInt($majorId);

        if (!$majorId) {
            return null;
        }

        if ($class->relationLoaded('major') && (int) ($class->major?->id ?? 0) === $majorId) {
            return $class->major;
        }

        $program = $class->programs->first(fn ($item) => (int) ($item->major_id ?? 0) === $majorId);

        return $program?->major ?? Major::find($majorId);
    }

    private function resolveShiftModel(Classes $class, mixed $shiftId): ?Shift
    {
        $shiftId = $this->toNullableInt($shiftId);

        if (!$shiftId) {
            return null;
        }

        if ($class->relationLoaded('shift') && (int) ($class->shift?->id ?? 0) === $shiftId) {
            return $class->shift;
        }

        $program = $class->programs->first(fn ($item) => (int) ($item->shift_id ?? 0) === $shiftId);

        return $program?->shift ?? Shift::find($shiftId);
    }

    private function studentMatchesMatrixFilters(Students $student, array $filters): bool
    {
        $academic = $student->academicInfo;

        $batchYear = $filters['batch_year'] ?? $filters['batch'] ?? null;
        if (!$this->sameScalar($academic?->batch_year, $batchYear)) {
            return false;
        }

        $studyDay = $filters['study_day'] ?? $filters['study_days'] ?? null;
        if (!$this->sameScalar($academic?->study_days, $studyDay)) {
            return false;
        }

        $majorId = $this->toNullableInt($filters['major_id'] ?? null);
        if ($majorId && (int) ($academic?->major_id ?? 0) !== $majorId) {
            return false;
        }

        $shiftId = $this->toNullableInt($filters['shift_id'] ?? null);
        if ($shiftId && (int) ($academic?->shift_id ?? 0) !== $shiftId) {
            return false;
        }

        $facultyId = $this->toNullableInt($filters['faculty_id'] ?? null);
        if ($facultyId && (int) ($academic?->major?->faculty_id ?? 0) !== $facultyId) {
            return false;
        }

        $yearLevel = $filters['year_level'] ?? $filters['stage'] ?? null;
        if (!$this->sameYearLevel($academic?->stage, $yearLevel)) {
            return false;
        }

        $studentName = $filters['student_name'] ?? $filters['search'] ?? null;
        if ($studentName) {
            $nameEn = strtolower($student->full_name_en ?? '');
            $nameKh = strtolower($student->full_name_kh ?? '');
            $code = strtolower($student->id_card_number ?? '');
            $q = strtolower($studentName);
            if (!str_contains($nameEn, $q) && !str_contains($nameKh, $q) && !str_contains($code, $q)) {
                return false;
            }
        }

        return true;
    }

    private function matrixSessionCount(mixed $value): int
    {
        $count = is_numeric($value) ? (int) $value : self::DEFAULT_MATRIX_SESSION_COUNT;

        return min(max($count, 1), self::MAX_MATRIX_SESSION_COUNT);
    }

    private function statusToUi(?string $status): string
    {
        return match ($this->normalizeStatus($status)) {
            'Absent' => 'A',
            'Late' => 'L',
            'Excused' => 'P',
            default => 'Att',
        };
    }

    private function sameScalar(mixed $actual, mixed $expected): bool
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        if ($actual === null || $actual === '') {
            return false;
        }

        return trim((string) $actual) === trim((string) $expected);
    }

    private function sameYearLevel(mixed $actual, mixed $expected): bool
    {
        if ($expected === null || $expected === '') {
            return true;
        }

        if ($actual === null || $actual === '') {
            return false;
        }

        $actualNumber = $this->toNullableYearLevel($actual);
        $expectedNumber = $this->toNullableYearLevel($expected);

        if ($actualNumber && $expectedNumber) {
            return $actualNumber === $expectedNumber;
        }

        return strtolower(trim((string) $actual)) === strtolower(trim((string) $expected));
    }

    private function matrixStatusToBackend(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            'att', 'present' => 'Present',
            'a', 'absent' => 'Absent',
            'l', 'late' => 'Late',
            'p', 'excused', 'excuse', 'permission', 'permit' => 'Excused',
            default => 'Present',
        };
    }

    private function successResponse(int $status, string $message, array $data): array
    {
        return [
            'status' => $status,
            'payload' => [
                'success' => true,
                'message' => $message,
                'data' => $data,
                'meta' => $this->meta(),
            ],
        ];
    }

    private function errorResponse(int $status, string $message, mixed $data = null): array
    {
        return [
            'status' => $status,
            'payload' => [
                'success' => false,
                'message' => $message,
                'data' => $data,
                'meta' => $this->meta(),
            ],
        ];
    }

    private function meta(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'api_version' => 'v1',
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            'present', 'p' => 'Present',
            'late', 'l' => 'Late',
            'excused', 'excuse', 'e' => 'Excused',
            'absent', 'a' => 'Absent',
            default => 'Absent',
        };
    }

    private function resolveStudentIdsFromRecords(array $records): array
    {
        $idCardNumbers = [];

        foreach ($records as $record) {
            $value = $record['student_id'] ?? null;

            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value === '' || is_numeric($value)) {
                continue;
            }

            if (preg_match('/^B(\d{6,})$/', $value)) {
                continue;
            }

            $idCardNumbers[] = $value;
        }

        $idCardMap = [];
        $idCardNumbers = array_values(array_unique($idCardNumbers));

        if (!empty($idCardNumbers)) {
            $idCardMap = Students::query()
                ->whereIn('id_card_number', $idCardNumbers)
                ->pluck('id', 'id_card_number')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $resolved = [];

        foreach ($records as $index => $record) {
            $value = $record['student_id'] ?? null;

            if (is_numeric($value)) {
                $resolved[$index] = (int) $value;
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    $resolved[$index] = null;
                    continue;
                }

                if (preg_match('/^B(\d{6,})$/', $value, $matches)) {
                    $id = (int) ltrim($matches[1], '0');
                    $resolved[$index] = $id > 0 ? $id : null;
                    continue;
                }

                $resolved[$index] = $idCardMap[$value] ?? null;
                continue;
            }

            $resolved[$index] = null;
        }

        return $resolved;
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            try {
                return \Carbon\Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return '';
    }

    private function formatDisplayDate(mixed $value): string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('d-m-Y');
        }

        if (is_string($value) && $value !== '') {
            try {
                return \Carbon\Carbon::parse($value)->format('d-m-Y');
            } catch (\Throwable $e) {
                return $value;
            }
        }

        return '';
    }

    private function formatStudentCode(int $studentId): string
    {
        return 'ST' . str_pad((string) $studentId, 3, '0', STR_PAD_LEFT);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function toNullableYearLevel(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $extracted = (int) filter_var((string) $value, FILTER_SANITIZE_NUMBER_INT);

        return $extracted > 0 ? $extracted : null;
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}




