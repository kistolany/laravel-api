<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\ClassResource;
use App\Models\Classes;
use App\Models\Students;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClassService extends BaseService
{
    public function index(): PaginatedResult
    {
        $query = Classes::query()->latest();

        return $this->paginateResponse($query, ClassResource::class);
    }

    public function findById(int $id, bool $withStudents = false): Classes
    {
        $query = Classes::query();

        if ($withStudents) {
            $query->with('students');
        }

        $class = $query->find($id);

        if (!$class) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Class with ID :$id not found.");
        }

        return $class;
    }

    public function create(array $data): Classes
    {
        $validatedData = $this->validateExisting($data);

        return Classes::create($validatedData);
    }

    public function addStudent(int $classId, array $data): Students
    {
        $validatedData = $this->validateStudentAssignment($data);
        $class = $this->findById($classId);

        $studentId = $this->resolveStudentId($validatedData['student_id']);
        $student = Students::find($studentId);

        if (!$student) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Student not found.");
        }

        $exists = $class->students()
            ->where('students.id', $studentId)
            ->exists();

        if ($exists) {
            throw new ApiException(ResponseStatus::EXISTING_DATA, "Student already exists in this class.");
        }

        $class->students()->attach($studentId, [
            'joined_date' => $validatedData['joined_date'],
            'left_date' => $validatedData['left_date'] ?? null,
            'status' => $validatedData['status'] ?? 'Active',
        ]);

        return $class->students()
            ->where('students.id', $studentId)
            ->firstOrFail();
    }

    public function addStudentsByMajor(int $classId, array $data): array
    {
        $validatedData = $this->validateBulkStudentAssignment($data);
        $class = $this->findById($classId);

        $majorId = $validatedData['major_id'] ?? $class->major_id;

        $studentIds = Students::whereHas('academicInfo', function ($q) use ($majorId) {
            $q->where('major_id', $majorId);
        })->pluck('id');

        if ($studentIds->isEmpty()) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "No students found for this major.");
        }

        $existingIds = $class->students()
            ->whereIn('students.id', $studentIds)
            ->pluck('students.id');

        $attachIds = $studentIds->diff($existingIds);

        $joinedDate = $validatedData['joined_date'] ?? now()->toDateString();
        $leftDate = $validatedData['left_date'] ?? null;
        $status = $validatedData['status'] ?? 'Active';

        if ($attachIds->isNotEmpty()) {
            $attachData = [];
            foreach ($attachIds as $id) {
                $attachData[$id] = [
                    'joined_date' => $joinedDate,
                    'left_date' => $leftDate,
                    'status' => $status,
                ];
            }
            $class->students()->syncWithoutDetaching($attachData);
        }

        return [
            'added_count' => $attachIds->count(),
            'skipped_count' => $existingIds->count(),
            'total_found' => $studentIds->count(),
        ];
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes', 'code')->ignore($ignoreId),
            ],
            'major_id' => 'required|exists:majors,id',
            'shift_id' => 'required|exists:shifts,id',
            'academic_year' => 'required|string|max:20',
            'year_level' => 'required|integer|min:1|max:8',
            'semester' => 'required|integer|min:1|max:3',
            'section' => 'required|string|max:10',
            'max_students' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Validation failed for class data.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }

    protected function validateStudentAssignment(array $data): array
    {
        $validator = Validator::make($data, [
            'student_id' => 'required',
            'joined_date' => 'required|date',
            'left_date' => 'nullable|date',
            'status' => 'nullable|in:Active,Suspended,Graduated,Dropped',
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Validation failed for class student data.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }

    protected function validateBulkStudentAssignment(array $data): array
    {
        $validator = Validator::make($data, [
            'major_id' => 'nullable|exists:majors,id',
            'joined_date' => 'nullable|date',
            'left_date' => 'nullable|date',
            'status' => 'nullable|in:Active,Suspended,Graduated,Dropped',
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Validation failed for class student data.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }

    private function resolveStudentId(mixed $value): int
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

        throw new ApiException(ResponseStatus::BAD_REQUEST, "Invalid student_id format.");
    }
}
