<?php

namespace App\Services\Class;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Class\ClassResource;
use App\Models\Classes;
use App\Models\Students;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class ClassService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = Classes::query()->latest();
            
            return $this->paginateResponse($query, ClassResource::class);
            
            
        });
    }

    public function findById(int $id, bool $withStudents = false): Classes
    {
        return $this->trace(__FUNCTION__, function () use ($id, $withStudents): Classes {
            $query = Classes::query();
            
            if ($withStudents) {
                $query->with('students');
            }
            
            $class = $query->find($id);
            
            if (!$class) {
                Log::warning('Class not found.', [
                    'id' => $id,
                    'with_students' => $withStudents,
                ]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Class with ID :$id not found.");
            }
            
            return $class;
            
            
        });
    }

    public function create(array $data): Classes
    {
        return $this->trace(__FUNCTION__, function () use ($data): Classes {
            $validatedData = $this->validateExisting($data);
            
            return Classes::create($validatedData);
            
            
        });
    }

    public function addStudent(int $classId, array $data): Students
    {
        return $this->trace(__FUNCTION__, function () use ($classId, $data): Students {
            $validatedData = $this->validateStudentAssignment($data);
            $class = $this->findById($classId);
            
            $studentId = $this->resolveStudentId($validatedData['student_id']);
            $student = Students::find($studentId);
            
            if (!$student) {
                Log::warning('Class addStudent failed: student not found.', [
                    'class_id' => $classId,
                    'student_id' => $studentId,
                ]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Student not found.");
            }
            
            $exists = $class->students()
                ->where('students.id', $studentId)
                ->exists();
            
            if ($exists) {
                Log::warning('Class addStudent failed: duplicate student in class.', [
                    'class_id' => $classId,
                    'student_id' => $studentId,
                ]);
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
            
            
        });
    }

    public function addStudentsByMajor(int $classId, array $data): array
    {
        return $this->trace(__FUNCTION__, function () use ($classId, $data): array {
            $validatedData = $this->validateBulkStudentAssignment($data);
            $class = $this->findById($classId);
            
            $majorId = $validatedData['major_id'];
            
            $studentIds = Students::whereHas('academicInfo', function ($q) use ($majorId) {
                $q->where('major_id', $majorId);
            })->pluck('id');
            
            if ($studentIds->isEmpty()) {
                Log::warning('Class addStudentsByMajor failed: no students found for major.', [
                    'class_id' => $classId,
                    'major_id' => $majorId,
                ]);
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
            
            
        });
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name')
                ?: 'Validation failed for class data.';

            Log::warning('Class validation failed.', [
                'ignore_id' => $ignoreId,
                'data' => [
                    'name' => $data['name'] ?? null,
                ],
                'errors' => $errors,
            ]);

            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
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
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('student_id')
                ?: $validator->errors()->first('joined_date')
                ?: 'Validation failed for class student data.';

            Log::warning('Class student assignment validation failed.', [
                'data' => [
                    'student_id' => $data['student_id'] ?? null,
                    'joined_date' => $data['joined_date'] ?? null,
                    'left_date' => $data['left_date'] ?? null,
                    'status' => $data['status'] ?? null,
                ],
                'errors' => $errors,
            ]);

            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
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
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('major_id')
                ?: $validator->errors()->first('joined_date')
                ?: 'Validation failed for class student data.';

            Log::warning('Class bulk student assignment validation failed.', [
                'data' => [
                    'major_id' => $data['major_id'] ?? null,
                    'joined_date' => $data['joined_date'] ?? null,
                    'left_date' => $data['left_date'] ?? null,
                    'status' => $data['status'] ?? null,
                ],
                'errors' => $errors,
            ]);

            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
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

        Log::warning('Invalid student_id format for class assignment.', [
            'value' => $value,
        ]);

        throw new ApiException(ResponseStatus::BAD_REQUEST, "Invalid student_id format.");
    }
}




