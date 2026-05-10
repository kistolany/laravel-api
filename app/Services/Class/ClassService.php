<?php

namespace App\Services\Class;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Class\ClassResource;
use App\Models\Classes;
use App\Models\ClassProgram;
use App\Models\ClassSchedule;
use App\Models\Students;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class ClassService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = Classes::query()
                ->with(['programs.major', 'programs.shift'])
                ->withCount(['classStudents as class_students_count' => function ($query) {
                    $query->where('status', 'Active');
                }])
                ->latest();

            return $this->paginateResponse($query, ClassResource::class);
        });
    }

    public function findById(int $id, bool $withStudents = false): Classes
    {
        return $this->trace(__FUNCTION__, function () use ($id, $withStudents): Classes {
            $query = Classes::query()
                ->with(['programs.major', 'programs.shift'])
                ->withCount(['classStudents as class_students_count' => function ($query) {
                    $query->where('status', 'Active');
                }]);

            if ($withStudents) {
                $query->with(['students' => function ($q) {
                    $q->wherePivot('status', 'Active')->with('academicInfo.major');
                }]);
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

    public function update(int $id, array $data): Classes
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Classes {
            $validatedData = $this->validateExisting($data, $id);
            $class = $this->findById($id);
            $class->update($validatedData);
            return $class;
        });
    }

    public function stats(int $classId): array
    {
        return $this->trace(__FUNCTION__, function () use ($classId): array {
            $class = Classes::with(['programs.major', 'programs.shift'])->findOrFail($classId);

            $genderCounts = DB::table('students')
                ->join('class_students', 'students.id', '=', 'class_students.student_id')
                ->where('class_students.class_id', $classId)
                ->whereIn('class_students.status', ['Active', 'active'])
                ->select('students.gender', DB::raw('COUNT(*) as count'))
                ->groupBy('students.gender')
                ->get()
                ->keyBy('gender');

            $male   = (int) ($genderCounts->get('Male')?->count   ?? 0);
            $female = (int) ($genderCounts->get('Female')?->count ?? 0);

            $programs = $class->programs->map(fn ($p) => [
                'id'            => $p->id,
                'major_id'      => $p->major_id,
                'major_name'    => $p->major?->name,
                'shift_id'      => $p->shift_id,
                'shift_name'    => $p->shift?->name,
                'year_level'    => $p->year_level,
                'semester'      => $p->semester,
                'academic_year' => $p->academic_year,
                'section'       => $p->section,
            ])->values();

            return [
                'male'     => $male,
                'female'   => $female,
                'total'    => $male + $female,
                'programs' => $programs,
            ];
        });
    }

    public function delete(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id): void {
            $class = $this->findById($id);
            $class->students()->detach();
            $class->schedules()->delete();
            $class->attendanceSessions()->delete();
            $class->delete();
        });
    }

    public function addProgram(int $classId, array $data): ClassProgram
    {
        return $this->trace(__FUNCTION__, function () use ($classId, $data): ClassProgram {
            $class = $this->findById($classId);
            $program = $class->programs()->create($this->validateProgramData($classId, $data));
            $program->load(['major', 'shift']);
            return $program;
        });
    }

    public function updateProgram(int $classId, int $programId, array $data): ClassProgram
    {
        return $this->trace(__FUNCTION__, function () use ($classId, $programId, $data): ClassProgram {
            $program = ClassProgram::where('class_id', $classId)->where('id', $programId)->first();
            if (!$program) {
                throw new ApiException(ResponseStatus::NOT_FOUND, "Program not found.");
            }

            $program->update($this->validateProgramData($classId, $data, $programId));
            ClassSchedule::where('class_program_id', $program->id)->update([
                'class_id' => $program->class_id,
                'shift_id' => $program->shift_id,
                'academic_year' => $program->academic_year,
                'year_level' => $program->year_level,
                'semester' => $program->semester,
            ]);

            $program->load(['major', 'shift']);
            return $program;
        });
    }

    public function removeProgram(int $classId, int $programId): void
    {
        $this->trace(__FUNCTION__, function () use ($classId, $programId): void {
            $program = ClassProgram::where('class_id', $classId)->where('id', $programId)->first();
            if (!$program) {
                throw new ApiException(ResponseStatus::NOT_FOUND, "Program not found.");
            }
            if (ClassSchedule::where('class_program_id', $programId)->exists()) {
                throw new ApiException(ResponseStatus::BAD_REQUEST, "This program is used by schedules. Update schedules before deleting it.");
            }
            $program->delete();
        });
    }

    public function removeStudent(int $classId, mixed $studentId): void
    {
        $this->trace(__FUNCTION__, function () use ($classId, $studentId): void {
            $class = $this->findById($classId);
            $resolvedId = $this->resolveStudentId($studentId);
            $class->students()->detach($resolvedId);
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
            'name'      => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
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

        $name = trim((string) $data['name']);
        $exists = Classes::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw new ApiException(ResponseStatus::EXISTING_DATA, "Class \"$name\" already exists.");
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

    protected function validateProgramData(int $classId, array $data, ?int $ignoreProgramId = null): array
    {
        $validator = Validator::make($data, [
            'major_id'      => 'required|exists:majors,id',
            'shift_id'      => 'required|exists:shifts,id',
            'academic_year' => 'required|string|max:20',
            'year_level'    => 'required|integer|min:1|max:6',
            'semester'      => 'required|integer|min:1|max:2',
            'section'       => 'nullable|string|max:50',
            'max_students'  => 'nullable|integer|min:0|max:10000',
        ]);

        if ($validator->fails()) {
            throw new ApiException(ResponseStatus::BAD_REQUEST, $validator->errors()->first());
        }

        $validated = $validator->validated();
        $duplicate = ClassProgram::query()
            ->where('class_id', $classId)
            ->where('major_id', $validated['major_id'])
            ->where('shift_id', $validated['shift_id'])
            ->where('academic_year', $validated['academic_year'])
            ->where('year_level', $validated['year_level'])
            ->where('semester', $validated['semester'])
            ->when($ignoreProgramId, fn ($query) => $query->where('id', '!=', $ignoreProgramId))
            ->exists();

        if ($duplicate) {
            throw new ApiException(ResponseStatus::EXISTING_DATA, "This class program already exists.");
        }

        return $validated;
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




