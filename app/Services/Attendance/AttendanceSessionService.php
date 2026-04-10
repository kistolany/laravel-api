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
use App\Models\MajorSubject;
use App\Models\Students;
use App\Models\Teacher;
use App\Services\Concerns\ServiceTraceable;
class AttendanceSessionService
{
    use ServiceTraceable;

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
                        'no' => count($rows) + 1,
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
                        'name_en' => $shift?->name_en,
                        'time_range' => $shift?->time_range,
                    ],
                    'students' => $students,
                    'summary' => $summary,
                ]
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
        $now = now();
        $rows = [];
        $errors = [];

        foreach ($data['records'] as $index => $record) {
            $resolvedId = $this->resolveStudentId($record['student_id']);

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

    private function resolveStudentId(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^B(\d{6,})$/', $value, $matches)) {
            $id = (int) ltrim($matches[1], '0');
            if ($id > 0) {
                return $id;
            }
        }

        if (is_string($value)) {
            $id = Students::where('id_card_number', $value)->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return null;
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
}




