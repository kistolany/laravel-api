<?php

namespace App\Services\Student;

use App\Models\Students;
use Carbon\CarbonImmutable;
use App\Services\Concerns\ServiceTraceable;
use Illuminate\Database\Eloquent\Builder;

class StudentCardService
{
    use ServiceTraceable;

    public function buildCardListResponse(array $filters = []): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $query = Students::query()
                ->with(['academicInfo.major', 'academicInfo.shift'])
                ->where('status', 'active');

            $this->applyCardFilters($query, $filters);

            $limit = $this->cardLimit($filters['limit'] ?? null);
            $issuedDate = CarbonImmutable::now();

            $cards = $query
                ->orderBy('full_name_en')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(fn (Students $student) => $this->buildCardPayload($student, $issuedDate))
                ->all();

            return $this->successResponse(200, 'Student cards retrieved successfully.', [
                'items' => $cards,
                'total' => count($cards),
            ]);
        });
    }

    public function buildStudentCardResponse(int $studentId): array
    {
        return $this->trace(__FUNCTION__, function () use ($studentId): array {
            $student = Students::with(['academicInfo.major', 'academicInfo.shift'])->find($studentId);
            
            if (!$student) {
                return [
                    'status' => 404,
                    'payload' => [
                        'message' => "Student with ID {$studentId} not found.",
                    ],
                ];
            }
            
            return [
                'status' => 200,
                'payload' => $this->buildCardPayload($student, CarbonImmutable::now()),
            ];
            
            
        });
    }

    public function buildMajorCardsResponse(string $major): array
    {
        return $this->trace(__FUNCTION__, function () use ($major): array {
            $query = Students::with(['academicInfo.major', 'academicInfo.shift']);
            
            if (ctype_digit($major)) {
                $majorId = (int) $major;
                $query->whereHas('academicInfo', function ($q) use ($majorId) {
                    $q->where('major_id', $majorId);
                });
            } else {
                $query->whereHas('academicInfo.major', function ($q) use ($major) {
                    $q->where('name', $major);
                });
            }
            
            $issuedDate = CarbonImmutable::now();
            
            $cards = $query->get()->map(function ($student) use ($issuedDate) {
                return $this->buildCardPayload($student, $issuedDate);
            })->all();
            
            if (empty($cards)) {
                return [
                    'status' => 404,
                    'payload' => [
                        'message' => 'No students found for the provided major.',
                    ],
                ];
            }
            
            return [
                'status' => 200,
                'payload' => $cards,
            ];
            
            
        });
    }

    private function buildCardPayload(Students $student, CarbonImmutable $issuedDate): array
    {
        $academic = $student->academicInfo;
        $major = $academic?->major;
        $shift = $academic?->shift;

        return [
            'id' => $student->id,
            'student_id' => $student->id,
            'id_card_number' => $student->id_card_number,
            'barcode' => $student->barcode,
            'full_name_en' => $student->full_name_en ?? '',
            'full_name_kh' => $student->full_name_kh ?? '',
            'FullName_en' => $student->full_name_en ?? '',
            'FullName_kh' => $student->full_name_kh ?? '',
            'major_id' => $major?->id,
            'major' => $major?->name ?? '',
            'Major_en' => $major?->name ?? '',
            'Major_kh' => $major?->name ?? '',
            'shift_id' => $shift?->id,
            'shift' => $shift?->name ?? '',
            'batch_year' => $academic?->batch_year,
            'stage' => $academic?->stage,
            'study_days' => $academic?->study_days,
            'image' => $student->image,
            'ISS' => $issuedDate->format('Y-m-d'),
            'EXP' => $issuedDate->addYear()->format('Y-m-d'),
        ];
    }

    private function applyCardFilters(Builder $query, array $filters): void
    {
        $studentId = $this->toNullableInt($filters['student_id'] ?? null);
        $majorId = $this->toNullableInt($filters['major_id'] ?? $filters['major'] ?? null);
        $shiftId = $this->toNullableInt($filters['shift_id'] ?? $filters['shift'] ?? null);
        $classId = $this->toNullableInt($filters['class_id'] ?? null);
        $batchYear = $this->toNullableString($filters['batch_year'] ?? $filters['batch'] ?? null);
        $yearLevel = $this->toNullableYearLevel($filters['year_level'] ?? $filters['year'] ?? $filters['stage'] ?? null);
        $semester = $this->toNullableInt($filters['semester'] ?? null);
        $search = $this->toNullableString($filters['search'] ?? $filters['name'] ?? $filters['name_kh'] ?? $filters['student_name'] ?? null);

        $query
            ->when($studentId, fn (Builder $q, int $value) => $q->whereKey($value))
            ->when($majorId || $shiftId || $batchYear || $yearLevel, function (Builder $q) use ($majorId, $shiftId, $batchYear, $yearLevel): void {
                $q->whereHas('academicInfo', function (Builder $academic) use ($majorId, $shiftId, $batchYear, $yearLevel): void {
                    $academic
                        ->when($majorId, fn (Builder $slot, int $value) => $slot->where('major_id', $value))
                        ->when($shiftId, fn (Builder $slot, int $value) => $slot->where('shift_id', $value))
                        ->when($batchYear, fn (Builder $slot, string $value) => $slot->where('batch_year', $value));

                    if ($yearLevel) {
                        $academic->where(function (Builder $stage) use ($yearLevel): void {
                            $stage
                                ->where('stage', (string) $yearLevel)
                                ->orWhere('stage', 'Year ' . $yearLevel);
                        });
                    }
                });
            })
            ->when($classId || $semester, fn (Builder $q): Builder => $this->applyClassFilters($q, $classId, $majorId, $shiftId, $yearLevel, $semester))
            ->when($search, function (Builder $q, string $value): void {
                $barcodeId = $this->toBarcodeStudentId($value);
                $q->where(function (Builder $student) use ($value, $barcodeId): void {
                    $student
                        ->where('full_name_en', 'like', "%{$value}%")
                        ->orWhere('full_name_kh', 'like', "%{$value}%")
                        ->orWhere('id_card_number', 'like', "%{$value}%");

                    if (ctype_digit($value)) {
                        $student->orWhere('id', (int) $value);
                    }

                    if ($barcodeId) {
                        $student->orWhere('id', $barcodeId);
                    }
                });
            });
    }

    private function applyClassFilters(
        Builder $query,
        ?int $classId,
        ?int $majorId,
        ?int $shiftId,
        ?int $yearLevel,
        ?int $semester
    ): Builder {
        return $query->whereHas('classes', function (Builder $class) use ($classId, $majorId, $shiftId, $yearLevel, $semester): void {
            $class
                ->where('class_students.status', 'Active')
                ->when($classId, fn (Builder $slot, int $value) => $slot->where('classes.id', $value));

            if (!$majorId && !$shiftId && !$yearLevel && !$semester) {
                return;
            }

            $class->whereHas('programs', function (Builder $program) use ($majorId, $shiftId, $yearLevel, $semester): void {
                $program
                    ->when($majorId, fn (Builder $slot, int $value) => $slot->where('major_id', $value))
                    ->when($shiftId, fn (Builder $slot, int $value) => $slot->where('shift_id', $value))
                    ->when($yearLevel, fn (Builder $slot, int $value) => $slot->where('year_level', $value))
                    ->when($semester, fn (Builder $slot, int $value) => $slot->where('semester', $value));
            });
        });
    }

    private function cardLimit(mixed $value): int
    {
        $limit = is_numeric($value) ? (int) $value : 60;

        return min(max($limit, 1), 200);
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

        $extracted = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);

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

    private function toBarcodeStudentId(string $value): ?int
    {
        $normalized = strtoupper(trim($value));

        if (!preg_match('/^B0*(\d+)$/', $normalized, $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    private function successResponse(int $status, string $message, array $data): array
    {
        return [
            'status' => $status,
            'payload' => [
                'success' => true,
                'message' => $message,
                'data' => $data,
                'meta' => [
                    'generated_at' => now()->toIso8601String(),
                    'api_version' => 'v1',
                ],
            ],
        ];
    }
}



