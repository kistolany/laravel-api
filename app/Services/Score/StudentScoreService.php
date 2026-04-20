<?php

namespace App\Services\Score;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Score\StudentScoreResource;
use App\Models\Classes;
use App\Models\StudentScore;
use App\Models\Students;
use App\Models\Subject;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentScoreService extends BaseService
{
    public function index(array $filters = []): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $filters = $this->normalizeFilters($filters);

            $students = Students::query()
                ->with([
                    'academicInfo.major.faculty',
                    'academicInfo.shift',
                    'classes.major',
                    'classes.shift',
                    'scores' => fn ($query) => $this->applyScoreFilters($query, $filters),
                    'scores.subject',
                    'scores.class',
                ])
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
                ->when($filters['major_id'], fn (Builder $query, int $majorId) =>
                    $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('major_id', $majorId))
                )
                ->when($filters['shift_id'], fn (Builder $query, int $shiftId) =>
                    $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('shift_id', $shiftId))
                )
                ->when($filters['batch_year'], fn (Builder $query, string $batchYear) =>
                    $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('batch_year', $batchYear))
                )
                ->when($filters['stage'], fn (Builder $query, string $stage) =>
                    $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('stage', $stage))
                )
                ->when($filters['study_days'], fn (Builder $query, string $studyDays) =>
                    $query->whereHas('academicInfo', fn (Builder $academicQuery) => $academicQuery->where('study_days', $studyDays))
                )
                ->when($filters['class_id'], fn (Builder $query, int $classId) =>
                    $query->whereHas('classes', fn (Builder $classQuery) => $classQuery->where('classes.id', $classId))
                )
                ->when($filters['semester'], fn (Builder $query, string $semester) =>
                    $query->where(function (Builder $subQuery) use ($semester) {
                        $subQuery->whereHas('classes', fn (Builder $classQuery) => $classQuery->where('semester', $semester))
                            ->orWhereHas('scores', fn (Builder $scoreQuery) => $scoreQuery->where('semester', $semester));
                    })
                )
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
            'semester' => $this->nullableString($filters['semester'] ?? null),
            'study_days' => $this->nullableString($filters['study_days'] ?? $filters['studyDay'] ?? null),
            'major_id' => $this->nullableInt($filters['major_id'] ?? $filters['major'] ?? null),
            'shift_id' => $this->nullableInt($filters['shift_id'] ?? $filters['shift'] ?? null),
            'class_id' => $this->nullableInt($filters['class_id'] ?? $filters['class'] ?? null),
            'subject_id' => $this->nullableInt($filters['subject_id'] ?? $filters['subject'] ?? null),
        ];
    }

    private function applyScoreFilters($query, array $filters)
    {
        return $query
            ->when($filters['class_id'], fn (Builder $scoreQuery, int $classId) => $scoreQuery->where('class_id', $classId))
            ->when($filters['subject_id'], fn (Builder $scoreQuery, int $subjectId) => $scoreQuery->where('subject_id', $subjectId))
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
            'key' => $student->barcode,
            'student_id' => $student->id,
            'barcode' => $student->barcode,
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
