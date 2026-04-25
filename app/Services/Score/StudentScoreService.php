<?php

namespace App\Services\Score;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Score\StudentScoreResource;
use App\Models\AttendanceRecord;
use App\Models\Classes;
use App\Models\ClassProgram;
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
    private const ATTENDANCE_MAX_SCORE = 10.0;
    private const SUBJECT_MAX_SCORE = 90.0;
    private const PASS_PERCENTAGE = 50.0;

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
                    'classes.programs.major',
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

    public function gradeBook(array $filters = []): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $filters = $this->normalizeFilters($filters);

            $gbYearInt = isset($filters['stage']) && $filters['stage']
                ? (is_numeric($filters['stage'])
                    ? (int) $filters['stage']
                    : (int) filter_var($filters['stage'], FILTER_SANITIZE_NUMBER_INT))
                : null;

            // ── 1. Resolve subjects for this major/year/semester ───────────────
            $subjects   = $this->resolveGradeBookSubjects($filters, $gbYearInt);
            $subjectIds = $subjects->pluck('id');

            // ── 2. Load students — use simple direct filters, NOT studentQuery()
            //       so we don't exclude students who have no scores/classes yet
            $students = $this->gradeBookStudentQuery($filters, $gbYearInt)
                ->with([
                    'academicInfo.major',
                    'academicInfo.shift',
                    'classes',
                    'classes.programs',
                    'scores' => function ($q) use ($subjectIds, $filters, $gbYearInt) {
                        // Only load scores for these subjects; no filter = load all
                        if ($subjectIds->isNotEmpty()) {
                            $q->whereIn('subject_id', $subjectIds);
                        }
                        if ($filters['class_id'])     $q->where('class_id', $filters['class_id']);
                        if ($gbYearInt)               $q->where('year_level', $gbYearInt);
                        if ($filters['semester'])     $q->where('semester', $filters['semester']);
                    },
                    'scores.subject',
                ])
                ->orderBy('full_name_en')
                ->get();

            // ── 3. Map each student → { info + scores keyed by subject_id } ────
            $items = $students->map(function (Students $student) use ($subjects, $filters) {
                $academic = $student->academicInfo;
                $class    = $this->resolveClass($student, $filters, null);
                $program  = $this->resolveProgram($class, $filters);
                $major    = $academic?->major ?? $program?->major;

                $yearInt = isset($filters['stage']) && $filters['stage']
                    ? (is_numeric($filters['stage'])
                        ? (int) $filters['stage']
                        : (int) filter_var($filters['stage'], FILTER_SANITIZE_NUMBER_INT))
                    : null;

                $scoresBySubject = ($student->relationLoaded('scores') ? $student->scores : collect())
                    ->keyBy('subject_id');

                // Build one entry per subject
                $scoreMap = $subjects->mapWithKeys(function ($subject) use ($scoresBySubject) {
                    $score = $scoresBySubject->get($subject->id);
                    return [$subject->id => [
                        'score_id'         => $score?->id,
                        'class_score'      => (float) ($score?->class_score      ?? 0),
                        'assignment_score' => (float) ($score?->assignment_score ?? 0),
                        'midterm_score'    => (float) ($score?->midterm_score    ?? 0),
                        'final_score'      => (float) ($score?->final_score      ?? 0),
                        'total'            => round((float) ($score?->total ?? 0), 2),
                    ]];
                })->all();

                return [
                    'key'          => (string) $student->id,
                    'student_id'   => $student->id,
                    'id_card_number' => $student->id_card_number,
                    'barcode' => $student->barcode,
                    'full_name_kh' => $student->full_name_kh,
                    'full_name_en' => $student->full_name_en,
                    'gender'       => $student->gender,
                    'batch_year'   => $academic?->batch_year,
                    'stage'        => $academic?->stage,
                    'major_id'     => $major?->id,
                    'major_name'   => $major?->name,
                    'class_id'     => $class?->id,
                    'class_name'   => $class?->name ?? $class?->code,
                    'year_level'   => $yearInt ? (string) $yearInt : null,
                    'semester'     => $filters['semester'] ?? null,
                    'scores'       => $scoreMap,
                ];
            })->values()->all();

            return [
                'subjects' => $subjects->map(fn (Subject $s) => [
                    'id'   => $s->id,
                    'code' => $s->subject_Code,
                    'name' => $s->name,
                ])->values()->all(),
                'students' => $items,
                'total'    => count($items),
            ];
        });
    }

    private function gradeBookStudentQuery(array $filters, ?int $yearInt): Builder
    {
        $hasClassId = !empty($filters['class_id']);
        $hasMajorId = !empty($filters['major_id']);

        return Students::query()
            ->whereIn('student_type', ['PAY', 'PASS'])
            // class enrollment filter
            ->when($hasClassId, fn (Builder $q) =>
                $q->whereHas('classes', fn (Builder $cq) => $cq->where('classes.id', $filters['class_id']))
            )
            // major filter — always match student's OWN academicInfo.major_id
            ->when($hasMajorId, fn (Builder $q) =>
                $q->whereHas('academicInfo', fn (Builder $aq) => $aq->where('major_id', $filters['major_id']))
            )
            // year filter — match academicInfo.stage OR enrolled class's programs
            ->when($yearInt, function (Builder $q) use ($filters, $yearInt, $hasClassId) {
                if ($hasClassId) {
                    $classId = $filters['class_id'];
                    $q->whereHas('classes', fn (Builder $cq) =>
                        $cq->where('classes.id', $classId)
                           ->whereHas('programs', fn (Builder $pq) => $pq->where('year_level', $yearInt))
                    );
                } else {
                    $q->where(function (Builder $sq) use ($filters, $yearInt) {
                        $sq->whereHas('academicInfo', fn (Builder $aq) => $aq->where('stage', $filters['stage']))
                           ->orWhereHas('classes.programs', fn (Builder $pq) => $pq->where('year_level', $yearInt));
                    });
                }
            })
            // semester filter — match enrolled class's programs
            ->when($filters['semester'], function (Builder $q) use ($filters, $hasClassId) {
                if ($hasClassId) {
                    $classId = $filters['class_id'];
                    $q->whereHas('classes', fn (Builder $cq) =>
                        $cq->where('classes.id', $classId)
                           ->whereHas('programs', fn (Builder $pq) => $pq->where('semester', $filters['semester']))
                    );
                } else {
                    $q->whereHas('classes.programs', fn (Builder $pq) => $pq->where('semester', $filters['semester']));
                }
            })
            ->when($filters['search'], function (Builder $q, string $search) {
                $q->where(function (Builder $sq) use ($search) {
                    $sq->where('full_name_kh', 'like', "%{$search}%")
                       ->orWhere('full_name_en', 'like', "%{$search}%")
                       ->orWhere('id_card_number', 'like', "%{$search}%");
                });
            });
    }

    private function resolveGradeBookSubjects(array $filters, ?int $yearInt = null): Collection
    {
        $majorId  = $filters['major_id'];
        $semester = $filters['semester'];

        // If class_id set but filters are incomplete, derive from class programs
        if ($filters['class_id'] && (!$majorId || !$yearInt || !$semester)) {
            $class = Classes::with('programs')->find($filters['class_id']);
            if ($class) {
                $program  = $this->resolveProgram($class, $filters);
                $majorId  = $majorId  ?? $program?->major_id;
                $yearInt  = $yearInt  ?? ($program ? (int) $program->year_level : null);
                $semester = $semester ?? ($program ? (string) $program->semester : null);
            }
        }

        // Need at least a major to look up subjects
        if (!$majorId) return collect();

        return Subject::query()
            ->whereIn('id', MajorSubject::query()
                ->where('major_id', $majorId)
                ->when($yearInt,  fn ($q) => $q->where('year_level', $yearInt))
                ->when($semester, fn ($q) => $q->where('semester', $semester))
                ->select('subject_id')
            )
            ->orderBy('subject_Code')
            ->orderBy('name')
            ->get();
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

    public function reexamResults(array $filters = []): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $results = $this->finalResults($filters);
            $subjectCount = count($results['subjects'] ?? []);

            $items = collect($results['items'] ?? [])
                ->map(fn (array $row): array => $this->withReexamMeta($row, $subjectCount))
                ->filter(fn (array $row): bool => (bool) ($row['needs_reexam'] ?? false))
                ->sortBy('full_name_en')
                ->values()
                ->all();

            $results['items'] = $items;
            $results['total'] = count($items);

            return $results;
        });
    }

    private function withReexamMeta(array $row, int $fallbackSubjectCount): array
    {
        $failedSubjects = $this->failedSubjects($row);
        $maxPossibleTotal = $this->maxPossibleFinalTotal($row, $fallbackSubjectCount);
        $finalTotal = (float) ($row['final_total'] ?? 0);
        $finalPercentage = $maxPossibleTotal > 0
            ? round(($finalTotal / $maxPossibleTotal) * 100, 2)
            : 0.0;

        $isDisqualified = (bool) ($row['is_disqualified'] ?? false);
        $hasFailedSubjects = count($failedSubjects) > 0;
        $hasLowFinalPercentage = $maxPossibleTotal > 0 && $finalPercentage < self::PASS_PERCENTAGE;

        $row['max_possible_total'] = $maxPossibleTotal;
        $row['final_percentage'] = $finalPercentage;
        $row['pass_percentage'] = self::PASS_PERCENTAGE;
        $row['subject_pass_score'] = $this->subjectPassScore();
        $row['failed_subjects'] = $failedSubjects;
        $row['needs_reexam'] = $isDisqualified || $hasLowFinalPercentage || $hasFailedSubjects;
        $row['reexam_reason'] = $this->reexamReason($isDisqualified, $hasLowFinalPercentage, $hasFailedSubjects);

        return $row;
    }

    private function failedSubjects(array $row): array
    {
        return collect($row['subjects'] ?? [])
            ->values()
            ->map(fn (array $subject, int $index): array => [
                'slot_index' => $index,
                'subject_id' => $subject['subject_id'] ?? null,
                'subject_code' => $subject['subject_code'] ?? null,
                'subject_name' => $subject['subject_name'] ?? null,
                'label' => $this->subjectLabel($subject),
                'total' => round((float) ($subject['total'] ?? 0), 2),
                'pass_score' => $this->subjectPassScore(),
                'percentage' => round(((float) ($subject['total'] ?? 0) / self::SUBJECT_MAX_SCORE) * 100, 2),
            ])
            ->filter(fn (array $subject): bool => $subject['total'] < $subject['pass_score'])
            ->values()
            ->all();
    }

    private function maxPossibleFinalTotal(array $row, int $fallbackSubjectCount): float
    {
        $subjectCount = max(count($row['subjects'] ?? []), $fallbackSubjectCount);

        if ($subjectCount <= 0) {
            return 0.0;
        }

        return round(self::ATTENDANCE_MAX_SCORE + ($subjectCount * self::SUBJECT_MAX_SCORE), 2);
    }

    private function subjectPassScore(): float
    {
        return round(self::SUBJECT_MAX_SCORE * (self::PASS_PERCENTAGE / 100), 2);
    }

    private function reexamReason(bool $isDisqualified, bool $hasLowFinalPercentage, bool $hasFailedSubjects): string
    {
        if ($isDisqualified) {
            return 'disqualified';
        }

        if ($hasLowFinalPercentage && $hasFailedSubjects) {
            return 'low_final_and_failed_subjects';
        }

        if ($hasLowFinalPercentage) {
            return 'low_final_percentage';
        }

        if ($hasFailedSubjects) {
            return 'failed_subjects';
        }

        return 'none';
    }

    private function subjectLabel(array $subject): string
    {
        $code = trim((string) ($subject['subject_code'] ?? ''));
        $name = trim((string) ($subject['subject_name'] ?? ''));

        return trim(($code ? $code . ' - ' : '') . $name) ?: 'Subject';
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
        $hasClassId  = !empty($filters['class_id']);
        $hasMajorId  = !empty($filters['major_id']);
        $yearInt     = isset($filters['stage'])
            ? (is_numeric($filters['stage'])
                ? (int) $filters['stage']
                : (int) filter_var($filters['stage'], FILTER_SANITIZE_NUMBER_INT))
            : null;

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
            // ── class_id: must be enrolled in exactly that class ──────────────
            ->when($hasClassId, fn (Builder $q) =>
                $q->whereHas('classes', fn (Builder $cq) => $cq->where('classes.id', $filters['class_id']))
            )
            // ── major_id: match student's OWN registered major only ──────────
            // When class_id is also set, major is enforced via class program below
            ->when($hasMajorId && !$hasClassId, fn (Builder $q) =>
                $q->whereHas('academicInfo', fn (Builder $aq) => $aq->where('major_id', $filters['major_id']))
            )
            // When class_id AND major_id set: student's OWN major must match (class enrollment handled above)
            ->when($hasMajorId && $hasClassId, fn (Builder $q) =>
                $q->whereHas('academicInfo', fn (Builder $aq) => $aq->where('major_id', $filters['major_id']))
            )
            // ── faculty_id ───────────────────────────────────────────────────
            ->when($filters['faculty_id'], fn (Builder $q, int $facultyId) =>
                $q->whereHas('academicInfo.major', fn (Builder $mq) => $mq->where('faculty_id', $facultyId))
            )
            // ── shift_id ─────────────────────────────────────────────────────
            ->when($filters['shift_id'], function (Builder $query, int $shiftId) use ($hasClassId) {
                if ($hasClassId) {
                    $query->whereHas('classes.programs', fn (Builder $q) => $q->where('shift_id', $shiftId));
                } else {
                    $query->where(function (Builder $sq) use ($shiftId) {
                        $sq->whereHas('academicInfo', fn (Builder $q) => $q->where('shift_id', $shiftId))
                           ->orWhereHas('classes.programs', fn (Builder $q) => $q->where('shift_id', $shiftId));
                    });
                }
            })
            // ── batch_year ───────────────────────────────────────────────────
            ->when($filters['batch_year'], fn (Builder $q, string $batchYear) =>
                $q->whereHas('academicInfo', fn (Builder $aq) => $aq->where('batch_year', $batchYear))
            )
            // ── year/stage ───────────────────────────────────────────────────
            ->when($filters['stage'] && $yearInt, function (Builder $query) use ($filters, $yearInt, $hasClassId) {
                if ($hasClassId) {
                    // scoped to class programs only
                    $query->whereHas('classes.programs', fn (Builder $q) => $q->where('year_level', $yearInt));
                } else {
                    $query->where(function (Builder $sq) use ($filters, $yearInt) {
                        $sq->whereHas('academicInfo', fn (Builder $q) => $q->where('stage', $filters['stage']))
                           ->orWhereHas('classes.programs', fn (Builder $q) => $q->where('year_level', $yearInt));
                    });
                }
            })
            // ── semester ─────────────────────────────────────────────────────
            ->when($filters['semester'], function (Builder $query, string $semester) use ($hasClassId) {
                if ($hasClassId) {
                    $query->whereHas('classes.programs', fn (Builder $q) => $q->where('semester', $semester));
                } else {
                    $query->where(function (Builder $sq) use ($semester) {
                        $sq->whereHas('classes.programs', fn (Builder $q) => $q->where('semester', $semester))
                           ->orWhereHas('scores', fn (Builder $q) => $q->where('semester', $semester));
                    });
                }
            })
            // ── study_days ───────────────────────────────────────────────────
            ->when($filters['study_days'], fn (Builder $q, string $studyDays) =>
                $q->whereHas('academicInfo', fn (Builder $aq) => $aq->where('study_days', $studyDays))
            )
            // ── academic_year ────────────────────────────────────────────────
            ->when($filters['academic_year'], function (Builder $query, string $academicYear) {
                $query->where(function (Builder $sq) use ($academicYear) {
                    $sq->whereHas('classes', fn (Builder $q) => $q->where('academic_year', $academicYear))
                       ->orWhereHas('scores', fn (Builder $q) => $q->where('academic_year', $academicYear));
                });
            })
            ->when($filters['subject_id'], function (Builder $query, int $subjectId) use ($filters, $yearInt, $hasClassId) {
                $query->where(function (Builder $subQuery) use ($subjectId, $filters, $yearInt, $hasClassId) {
                    // Match via student's own major (academicInfo)
                    $subQuery->whereHas('academicInfo.major', function (Builder $mq) use ($subjectId, $yearInt, $filters) {
                        $mq->whereHas('majorSubjects', function (Builder $msq) use ($subjectId, $yearInt, $filters) {
                            $msq->where('major_subjects.subject_id', $subjectId);
                            if ($yearInt) $msq->where('major_subjects.year_level', $yearInt);
                            if ($filters['semester']) $msq->where('major_subjects.semester', $filters['semester']);
                        });
                    });
                    // OR via class programs major (when class_id selected)
                    if ($hasClassId) {
                        $subQuery->orWhereHas('classes.programs.major', function (Builder $mq) use ($subjectId, $yearInt, $filters) {
                            $mq->whereHas('majorSubjects', function (Builder $msq) use ($subjectId, $yearInt, $filters) {
                                $msq->where('major_subjects.subject_id', $subjectId);
                                if ($yearInt) $msq->where('major_subjects.year_level', $yearInt);
                                if ($filters['semester']) $msq->where('major_subjects.semester', $filters['semester']);
                            });
                        });
                    }
                });
            });
    }

    private function applyScoreFilters($query, array $filters)
    {
        return $query
            ->when($filters['class_id'], fn (Builder $scoreQuery, int $classId) => $scoreQuery->where('class_id', $classId))
            ->when($filters['subject_id'], fn (Builder $scoreQuery, int $subjectId) => $scoreQuery->where('subject_id', $subjectId))
            ->when($filters['academic_year'], fn (Builder $scoreQuery, string $academicYear) => $scoreQuery->where('academic_year', $academicYear))
            ->when($filters['stage'], function (Builder $scoreQuery, string $stage) {
                $yearInt = is_numeric($stage) ? (int) $stage : (int) filter_var($stage, FILTER_SANITIZE_NUMBER_INT);
                $scoreQuery->where('year_level', $yearInt);
            })
            ->when($filters['semester'], fn (Builder $scoreQuery, string $semester) => $scoreQuery->where('semester', $semester))
            ->latest('updated_at');
    }

    private function mapStudentScoreRow(Students $student, array $filters, ?Subject $selectedSubject): array
    {
        $score    = $this->resolveScore($student, $filters);
        $class    = $this->resolveClass($student, $filters, $score);
        $academic = $student->academicInfo;

        // Resolve the matching class program based on filter context
        $program  = $this->resolveProgram($class, $filters);

        // Major: prefer student's own academicInfo, then class program, then class direct
        $major = $academic?->major ?? $program?->major ?? $class?->major;
        $shift = $academic?->shift ?? $class?->shift;
        $subject = $selectedSubject ?? $score?->subject;

        // Year/semester: filter context wins (what user searched) > score > class_program > class columns > academic stage
        $yearInt = isset($filters['stage']) && $filters['stage']
            ? (is_numeric($filters['stage'])
                ? (int) $filters['stage']
                : (int) filter_var($filters['stage'], FILTER_SANITIZE_NUMBER_INT))
            : null;

        $yearLevel = $yearInt
            ?? $score?->year_level
            ?? $program?->year_level
            ?? $class?->year_level;

        $semester = $filters['semester']
            ?? $score?->semester
            ?? $program?->semester
            ?? $class?->semester;

        $classScore      = (float) ($score?->class_score      ?? 0);
        $assignmentScore = (float) ($score?->assignment_score ?? 0);
        $midtermScore    = (float) ($score?->midterm_score    ?? 0);
        $finalScore      = (float) ($score?->final_score      ?? 0);

        return [
            'key' => (string) $student->id,
            'student_id' => $student->id,
            'id_card_number' => $student->id_card_number,
            'barcode' => $student->barcode,
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
            'year_level' => $yearLevel ? (string) $yearLevel : null,
            'semester' => $semester ? (string) $semester : null,
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
            'barcode' => $student->barcode,
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
            ->when($filters['stage'], function (Builder $sessionQuery) use ($filters) {
                $yearInt = is_numeric($filters['stage']) ? (int) $filters['stage'] : (int) filter_var($filters['stage'], FILTER_SANITIZE_NUMBER_INT);
                if ($yearInt > 0) $sessionQuery->where('year_level', $yearInt);
            })
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
        });
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

    private function resolveProgram(?Classes $class, array $filters): ?ClassProgram
    {
        if (!$class) return null;

        $programs = $class->relationLoaded('programs') ? $class->programs : collect();
        if ($programs->isEmpty()) return null;

        // Find the program that best matches the active filters
        return $programs->first(function ($program) use ($filters) {
            if ($filters['major_id'] && (int) $program->major_id !== $filters['major_id']) return false;
            if ($filters['semester'] && (string) $program->semester !== (string) $filters['semester']) return false;
            if (!empty($filters['stage'])) {
                $yearInt = is_numeric($filters['stage'])
                    ? (int) $filters['stage']
                    : (int) filter_var($filters['stage'], FILTER_SANITIZE_NUMBER_INT);
                if ($yearInt && (int) $program->year_level !== $yearInt) return false;
            }
            return true;
        }) ?? $programs->first();
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
