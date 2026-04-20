<?php

namespace App\Services\Score;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Score\StudentScoreResource;
use App\Models\AttendanceRecord;
use App\Models\Classes;
use App\Models\MajorSubject;
use App\Models\StudentScore;
use App\Models\Students;
use App\Models\Subject;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentScoreService extends BaseService
{
    public function index(array $filters = []): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $filters = $this->normalizeFilters($filters);

            $students = $this->studentQuery($filters)
                ->with([
                    'academicInfo.major.faculty',
                    'academicInfo.shift',
                    'classes.major',
                    'classes.shift',
                    'scores' => fn ($query) => $this->applyScoreFilters($query, $filters),
                    'scores.subject',
                    'scores.class',
                ])
                ->orderBy('full_name_en')
                ->get();

            $subject = $filters['subject_id'] ? Subject::find($filters['subject_id']) : null;

            return [
                'items' => $students
                    ->map(fn (Students $student) => $this->mapStudentScoreRow($student, $filters, $subject))
                    ->values()
                    ->all(),
                'total' => $students->count(),
            ];
        });
    }

    public function finalResults(array $filters = []): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $filters = $this->normalizeFilters($filters);

            $students = $this->studentQuery($filters)
                ->with([
                    'academicInfo.major.faculty',
                    'academicInfo.shift',
                    'classes.major',
                    'classes.shift',
                    'scores' => fn ($query) => $this->applyScoreFilters($query, $filters)
                        ->whereNotNull('subject_id')
                        ->with(['subject', 'class'])
                        ->latest('updated_at'),
                ])
                ->whereHas('scores', fn (Builder $query) => $this->applyScoreFilters($query, $filters))
                ->orderBy('full_name_en')
                ->get();

            $subjects = $this->resolveFinalSubjects($students, $filters);
            $attendanceScores = $this->buildAttendanceScores($students, $filters);

            return [
                'items' => $students
                    ->map(fn (Students $student) => $this->mapFinalResultRow($student, $filters, $subjects, $attendanceScores))
                    ->values()
                    ->all(),
                'subjects' => $subjects,
                'total' => $students->count(),
            ];
        });
    }

    public function bulkUpsert(array $records): array
    {
        return $this->trace(__FUNCTION__, function () use ($records): array {
            $saved = [];

            DB::transaction(function () use ($records, &$saved): void {
                foreach ($records as $record) {
                    $studentId = $this->resolveStudentId($record['student_id']);
                    $class = $this->findClass($record['class_id'] ?? null);

                    $context = [
                        'student_id' => $studentId,
                        'class_id' => $record['class_id'] ?? null,
                        'subject_id' => $record['subject_id'] ?? null,
                        'academic_year' => $record['academic_year'] ?? $class?->academic_year,
                        'year_level' => $record['year_level'] ?? ($class?->year_level ? (string) $class->year_level : null),
                        'semester' => $record['semester'] ?? ($class?->semester ? (string) $class->semester : null),
                    ];

                    $score = StudentScore::updateOrCreate($context, [
                        'class_score' => $this->scoreValue($record['class_score'] ?? 0),
                        'assignment_score' => $this->scoreValue($record['assignment_score'] ?? 0),
                        'midterm_score' => $this->scoreValue($record['midterm_score'] ?? 0),
                        'final_score' => $this->scoreValue($record['final_score'] ?? 0),
                    ]);

                    $saved[] = $score->load(['student', 'class', 'subject']);
                }
            });

            return [
                'saved_count' => count($saved),
                'items' => StudentScoreResource::collection(collect($saved))->resolve(),
            ];
        });
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'search' => $this->nullableString($filters['search'] ?? $filters['name'] ?? null),
            'batch_year' => $this->nullableString($filters['batch_year'] ?? $filters['batch'] ?? null),
            'stage' => $this->nullableString($filters['stage'] ?? $filters['year'] ?? $filters['year_level'] ?? null),
            'academic_year' => $this->nullableString($filters['academic_year'] ?? $filters['study_year'] ?? $filters['studyYear'] ?? null),
            'semester' => $this->nullableString($filters['semester'] ?? null),
            'study_days' => $this->nullableString($filters['study_days'] ?? $filters['studyDay'] ?? null),
            'faculty_id' => $this->nullableInt($filters['faculty_id'] ?? $filters['faculty'] ?? null),
            'major_id' => $this->nullableInt($filters['major_id'] ?? $filters['major'] ?? null),
            'shift_id' => $this->nullableInt($filters['shift_id'] ?? $filters['shift'] ?? null),
            'class_id' => $this->nullableInt($filters['class_id'] ?? $filters['class'] ?? null),
            'subject_id' => $this->nullableInt($filters['subject_id'] ?? $filters['subject'] ?? null),
        ];
    }

    private function studentQuery(array $filters): Builder
    {
        return Students::query()
            ->whereIn('student_type', ['PAY', 'PASS'])
            ->when($filters['search'], function (Builder $query, string $search) {
                $query->where(function (Builder $subQuery) use ($search) {
                    $subQuery->where('full_name_kh', 'like', "%{$search}%")
                        ->orWhere('full_name_en', 'like', "%{$search}%")
                        ->orWhere('id_card_number', 'like', "%{$search}%");

                    if (preg_match('/^B(\d{1,})$/i', $search, $matches)) {
                        $subQuery->orWhereKey((int) ltrim($matches[1], '0'));
                    }
                });
            })
            ->when($filters['faculty_id'], function (Builder $query, int $facultyId) {
                $query->where(function (Builder $subQuery) use ($facultyId) {
                    $subQuery->whereHas('academicInfo.major', fn (Builder $majorQuery) => $majorQuery->where('faculty_id', $facultyId))
                        ->orWhereHas('classes.major', fn (Builder $majorQuery) => $majorQuery->where('faculty_id', $facultyId));
                });
            })
            ->when($filters['major_id'], function (Builder $query, int $majorId) {
                $query->where(function (Builder $subQuery) use ($majorId) {
                    $subQuery->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('major_id', $majorId))
                        ->orWhereHas('classes', fn (Builder $classQuery) => $classQuery->where('classes.major_id', $majorId));
                });
            })
            ->when($filters['shift_id'], function (Builder $query, int $shiftId) {
                $query->where(function (Builder $subQuery) use ($shiftId) {
                    $subQuery->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('shift_id', $shiftId))
                        ->orWhereHas('classes', fn (Builder $classQuery) => $classQuery->where('classes.shift_id', $shiftId));
                });
            })
            ->when($filters['batch_year'], fn (Builder $query, string $batchYear) =>
                $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('batch_year', $batchYear))
            )
            ->when($filters['stage'], function (Builder $query, string $stage) {
                $query->where(function (Builder $subQuery) use ($stage) {
                    $subQuery->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('stage', $stage))
                        ->orWhereHas('classes', fn (Builder $classQuery) => $classQuery->where('classes.year_level', $stage))
                        ->orWhereHas('scores', fn (Builder $scoreQuery) => $scoreQuery->where('year_level', $stage));
                });
            })
            ->when($filters['study_days'], fn (Builder $query, string $studyDays) =>
                $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('study_days', $studyDays))
            )
            ->when($filters['class_id'], fn (Builder $query, int $classId) =>
                $query->whereHas('classes', fn (Builder $classQuery) => $classQuery->where('classes.id', $classId))
            )
            ->when($filters['academic_year'], function (Builder $query, string $academicYear) {
                $query->where(function (Builder $subQuery) use ($academicYear) {
                    $subQuery->whereHas('classes', fn (Builder $classQuery) => $classQuery->where('academic_year', $academicYear))
                        ->orWhereHas('scores', fn (Builder $scoreQuery) => $scoreQuery->where('academic_year', $academicYear));
                });
            })
            ->when($filters['semester'], function (Builder $query, string $semester) {
                $query->where(function (Builder $subQuery) use ($semester) {
                    $subQuery->whereHas('classes', fn (Builder $classQuery) => $classQuery->where('semester', $semester))
                        ->orWhereHas('scores', fn (Builder $scoreQuery) => $scoreQuery->where('semester', $semester));
                });
            });
    }

    private function applyScoreFilters($query, array $filters)
    {
        return $query
            ->when($filters['class_id'], fn (Builder $scoreQuery, int $classId) => $scoreQuery->where('class_id', $classId))
            ->when($filters['subject_id'], fn (Builder $scoreQuery, int $subjectId) => $scoreQuery->where('subject_id', $subjectId))
            ->when($filters['academic_year'], fn (Builder $scoreQuery, string $academicYear) => $scoreQuery->where('academic_year', $academicYear))
            ->when($filters['stage'], fn (Builder $scoreQuery, string $stage) => $scoreQuery->where('year_level', $stage))
            ->when($filters['semester'], fn (Builder $scoreQuery, string $semester) => $scoreQuery->where('semester', $semester))
            ->latest('updated_at');
    }

    private function mapStudentScoreRow(Students $student, array $filters, ?Subject $selectedSubject): array
    {
        $score = $this->resolveScore($student, $filters);
        $class = $this->resolveClass($student, $filters, $score);
        $academic = $student->academicInfo;
        $major = $academic?->major ?? $class?->major;
        $shift = $academic?->shift ?? $class?->shift;
        $subject = $selectedSubject ?? $score?->subject;

        $classScore = (float) ($score?->class_score ?? 0);
        $assignmentScore = (float) ($score?->assignment_score ?? 0);
        $midtermScore = (float) ($score?->midterm_score ?? 0);
        $finalScore = (float) ($score?->final_score ?? 0);

        return [
            'key' => (string) $student->id,
            'student_id' => $student->id,
            'id_card_number' => $student->id_card_number,
            'full_name_kh' => $student->full_name_kh,
            'full_name_en' => $student->full_name_en,
            'gender' => $student->gender,
            'dob' => $this->formatDate($student->dob),
            'student_type' => $student->student_type,
            'batch_year' => $academic?->batch_year,
            'stage' => $academic?->stage,
            'study_days' => $academic?->study_days,
            'major_id' => $major?->id,
            'major_name' => $major?->name,
            'faculty_id' => $major?->faculty?->id,
            'faculty_name' => $major?->faculty?->name,
            'shift_id' => $shift?->id,
            'shift_name' => $shift?->name,
            'class_id' => $class?->id,
            'class_name' => $class?->name ?? $class?->code,
            'subject_id' => $filters['subject_id'] ?? $score?->subject_id,
            'subject_name' => $subject?->name,
            'academic_year' => $score?->academic_year ?? $class?->academic_year,
            'year_level' => $score?->year_level ?? ($class?->year_level ? (string) $class->year_level : $academic?->stage),
            'semester' => $score?->semester ?? ($class?->semester ? (string) $class->semester : $filters['semester']),
            'score_id' => $score?->id,
            'class_score' => $classScore,
            'assignment_score' => $assignmentScore,
            'midterm_score' => $midtermScore,
            'final_score' => $finalScore,
            'total' => round($classScore + $assignmentScore + $midtermScore + $finalScore, 2),
        ];
    }

    private function mapFinalResultRow(Students $student, array $filters, array $subjects, Collection $attendanceScores): array
    {
        $scores = $student->relationLoaded('scores') ? $student->scores : collect();
        $firstScore = $scores->first();
        $class = $this->resolveClass($student, $filters, $firstScore);
        $academic = $student->academicInfo;
        $major = $academic?->major ?? $class?->major;
        $shift = $academic?->shift ?? $class?->shift;
        $scoreGroups = $scores->groupBy('subject_id');

        $subjectRows = collect($subjects)->map(function (array $subject) use ($scoreGroups) {
            $score = $scoreGroups->get($subject['id'], collect())->first();

            return [
                'subject_id' => $subject['id'],
                'subject_code' => $subject['code'],
                'subject_name' => $subject['name'],
                'score_id' => $score?->id,
                'total' => round((float) ($score?->total ?? 0), 2),
            ];
        })->values();

        $attendanceScore = round((float) $attendanceScores->get($student->id, 0), 2);
        $subjectTotal = round((float) $subjectRows->sum('total'), 2);

        return [
            'key' => (string) $student->id,
            'student_id' => $student->id,
            'id_card_number' => $student->id_card_number,
            'full_name_kh' => $student->full_name_kh,
            'full_name_en' => $student->full_name_en,
            'gender' => $student->gender,
            'dob' => $this->formatDate($student->dob),
            'student_type' => $student->student_type,
            'batch_year' => $academic?->batch_year,
            'stage' => $academic?->stage,
            'study_days' => $academic?->study_days,
            'major_id' => $major?->id,
            'major_name' => $major?->name,
            'faculty_id' => $major?->faculty?->id,
            'faculty_name' => $major?->faculty?->name,
            'shift_id' => $shift?->id,
            'shift_name' => $shift?->name,
            'class_id' => $class?->id,
            'class_name' => $class?->name ?? $class?->code,
            'academic_year' => $firstScore?->academic_year ?? $class?->academic_year,
            'year_level' => $firstScore?->year_level ?? ($class?->year_level ? (string) $class->year_level : $academic?->stage),
            'semester' => $firstScore?->semester ?? ($class?->semester ? (string) $class->semester : $filters['semester']),
            'attendance_score' => $attendanceScore,
            'subjects' => $subjectRows->all(),
            'score_ids' => $scores->pluck('id')->filter()->values()->all(),
            'final_total' => round($attendanceScore + $subjectTotal, 2),
            'is_disqualified' => $attendanceScore <= 0 && $subjectTotal <= 0,
        ];
    }

    private function resolveFinalSubjects(Collection $students, array $filters): array
    {
        $subjects = $students
            ->flatMap(fn (Students $student) => $student->relationLoaded('scores') ? $student->scores : collect())
            ->filter(fn (StudentScore $score) => $score->subject_id && $score->relationLoaded('subject') && $score->subject)
            ->unique('subject_id')
            ->sortBy(fn (StudentScore $score) => $score->subject?->subject_Code ?: $score->subject?->name)
            ->take(5)
            ->map(fn (StudentScore $score) => $this->mapSubjectHeader($score->subject))
            ->values()
            ->all();

        if (!empty($subjects) || !$filters['major_id']) {
            return $subjects;
        }

        return MajorSubject::query()
            ->with('subject')
            ->where('major_id', $filters['major_id'])
            ->when($filters['stage'], fn (Builder $query, string $stage) => $query->where('year_level', $stage))
            ->when($filters['semester'], fn (Builder $query, string $semester) => $query->where('semester', $semester))
            ->orderBy('subject_id')
            ->limit(5)
            ->get()
            ->pluck('subject')
            ->filter()
            ->map(fn (Subject $subject) => $this->mapSubjectHeader($subject))
            ->values()
            ->all();
    }

    private function mapSubjectHeader(Subject $subject): array
    {
        return [
            'id' => $subject->id,
            'code' => $subject->subject_Code,
            'name' => $subject->name,
            'label' => trim(($subject->subject_Code ? $subject->subject_Code . ' - ' : '') . ($subject->name ?? '')),
        ];
    }

    private function buildAttendanceScores(Collection $students, array $filters): Collection
    {
        $studentIds = $students->pluck('id')->filter()->values();

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return AttendanceRecord::query()
            ->select('student_id', 'status')
            ->whereIn('student_id', $studentIds)
            ->whereHas('session', fn (Builder $query) => $this->applyAttendanceSessionFilters($query, $filters))
            ->get()
            ->groupBy('student_id')
            ->map(function (Collection $records): float {
                $total = $records->count();

                if ($total === 0) {
                    return 0;
                }

                $earned = $records->sum(fn (AttendanceRecord $record) => $this->attendanceWeight($record->status));

                return round(($earned / $total) * 10, 2);
            });
    }

    private function applyAttendanceSessionFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['class_id'], fn (Builder $sessionQuery, int $classId) => $sessionQuery->where('class_id', $classId))
            ->when($filters['major_id'], fn (Builder $sessionQuery, int $majorId) => $sessionQuery->where('major_id', $majorId))
            ->when($filters['shift_id'], fn (Builder $sessionQuery, int $shiftId) => $sessionQuery->where('shift_id', $shiftId))
            ->when($filters['academic_year'], fn (Builder $sessionQuery, string $academicYear) => $sessionQuery->where('academic_year', $academicYear))
            ->when($filters['stage'] && is_numeric($filters['stage']), fn (Builder $sessionQuery) => $sessionQuery->where('year_level', (int) $filters['stage']))
            ->when($filters['semester'] && is_numeric($filters['semester']), fn (Builder $sessionQuery) => $sessionQuery->where('semester', (int) $filters['semester']));
    }

    private function attendanceWeight(?string $status): float
    {
        return match (strtolower(trim((string) $status))) {
            'present', 'att' => 1,
            'late', 'l' => 0.5,
            default => 0,
        };
    }

    private function resolveScore(Students $student, array $filters): ?StudentScore
    {
        $scores = $student->relationLoaded('scores') ? $student->scores : collect();

        if ($scores->isEmpty()) {
            return null;
        }

        return $scores->first(function (StudentScore $score) use ($filters) {
            if ($filters['class_id'] && (int) $score->class_id !== $filters['class_id']) {
                return false;
            }

            if ($filters['subject_id'] && (int) $score->subject_id !== $filters['subject_id']) {
                return false;
            }

            if ($filters['semester'] && (string) $score->semester !== (string) $filters['semester']) {
                return false;
            }

            return true;
        }) ?? $scores->first();
    }

    private function resolveClass(Students $student, array $filters, ?StudentScore $score): ?Classes
    {
        $classes = $student->relationLoaded('classes') ? $student->classes : collect();

        if ($filters['class_id']) {
            return $classes->firstWhere('id', $filters['class_id']);
        }

        if ($score?->class_id) {
            return $classes->firstWhere('id', $score->class_id) ?? $score->class;
        }

        return $classes->first();
    }

    private function resolveStudentId(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^B(\d{6,})$/i', trim($value), $matches)) {
            $id = (int) ltrim($matches[1], '0');
            if ($id > 0) {
                return $id;
            }
        }

        if (is_string($value)) {
            $id = Students::where('id_card_number', trim($value))->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        Log::warning('Invalid student_id format for score upsert.', ['value' => $value]);
        throw new ApiException(ResponseStatus::BAD_REQUEST, 'Invalid student_id format.');
    }

    private function findClass(mixed $classId): ?Classes
    {
        $id = $this->nullableInt($classId);
        return $id ? Classes::find($id) : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function scoreValue(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (!$value) {
            return null;
        }

        return substr((string) $value, 0, 10);
    }
}
