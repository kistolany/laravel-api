<?php

namespace App\Services\Major;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Major\MajorSubjectResource;
use App\Models\Classes;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class Major_subject_service extends BaseService
{

    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            // Start query on the junction table
            $query = MajorSubject::query()->with(['major', 'subject'])->latest();
            
            // Handle filters: Allow Admin to select by Major, Year, or Semester
            $query->when(request('major_id'), fn($q, $id) => $q->where('major_id', $id))
                ->when(request('year_level'), fn($q, $year) => $q->where('year_level', $year))
                ->when(request('semester'), fn($q, $sem) => $q->where('semester', $sem));
            
            return $this->paginateResponse($query, MajorSubjectResource::class);
            
            
        });
    }

    public function getByMajor(int $majorId)
    {
        return $this->trace(__FUNCTION__, function () use ($majorId) {
            return MajorSubject::with(['major', 'subject'])
                ->where('major_id', $majorId)
                ->orderBy('year_level')
                ->orderBy('semester')
                ->get();
            
            
        });
    }

    public function getByClass(int $classId)
    {
        return $this->trace(__FUNCTION__, function () use ($classId) {
            $class = $this->findClass($classId);

            // A class can have multiple programs (major+year+semester via class_programs)
            // Collect all subject slots that match any of the class's programs
            $programs = $class->programs()->get();

            if ($programs->isEmpty()) {
                return Subject::with([])->orderBy('name')->get()->map(fn ($s) => (object)[
                    'id' => null, 'major_id' => null, 'subject_id' => $s->id, 'year_level' => null, 'semester' => null,
                    'major' => null, 'subject' => $s,
                ]);
            }

            // Build OR conditions for each program
            return MajorSubject::with(['major', 'subject'])
                ->where(function ($query) use ($programs) {
                    foreach ($programs as $program) {
                        $query->orWhere(function ($q) use ($program) {
                            $q->where('major_id', $program->major_id)
                              ->when($program->year_level, fn ($q) => $q->where('year_level', $program->year_level))
                              ->when($program->semester,   fn ($q) => $q->where('semester',   $program->semester));
                        });
                    }
                })
                ->orderBy('major_id')->orderBy('year_level')->orderBy('semester')->orderBy('subject_id')
                ->get();
        });
    }

    public function findById(int $id): MajorSubject
    {
        return $this->trace(__FUNCTION__, function () use ($id): MajorSubject {
            // find id method with relationships
            $assignment = MajorSubject::with(['major', 'subject'])->find($id);
            
            // Validate exist id
            if (!$assignment) {
                Log::warning('Major-subject assignment not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Assignment not found.");
            }
            
            return $assignment;
            
            
        });
    }


    public function create(array $data): MajorSubject
    {
        return $this->trace(__FUNCTION__, function () use ($data): MajorSubject {
            return DB::transaction(function () use ($data) {

                // Resolve major name → major_id (frontend sends major name string)
                if (empty($data['major_id']) && !empty($data['major'])) {
                    $major = Major::where('name', $data['major'])->first();
                    if (!$major) {
                        throw new ApiException(ResponseStatus::NOT_FOUND, "Major '{$data['major']}' not found.");
                    }
                    $data['major_id'] = $major->id;
                }

                // Map frontend 'year' → 'year_level'
                if (empty($data['year_level']) && !empty($data['year'])) {
                    $data['year_level'] = $data['year'];
                }

                // Find or create subject by name
                if (empty($data['subject_id']) && !empty($data['name'])) {
                    $subject = Subject::firstOrCreate(
                        ['name' => $data['name']],
                        ['subject_Code' => $data['subject_Code'] ?? null]
                    );
                    $data['subject_id'] = $subject->id;
                }

                $validatedData = $this->validateAssignment($data);
                return MajorSubject::create($validatedData);
            });
        });
    }

    public function createFromClass(int $classId, array $data): MajorSubject
    {
        return $this->trace(__FUNCTION__, function () use ($classId, $data): MajorSubject {
            $class = $this->findClass($classId);

            // If major_id/year_level/semester provided in data, use them directly
            // Otherwise resolve from class programs (preferred) or class columns
            if (empty($data['major_id'])) {
                $program = $class->programs()->first();
                $data['major_id']   = $program?->major_id;
                $data['year_level'] = $data['year_level']   ?? $program?->year_level;
                $data['semester']   = $data['semester']     ?? $program?->semester;
            }

            return $this->create($data);
        });
    }

    public function update(int $id, array $data): MajorSubject
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): MajorSubject {
            $assignment = $this->findById($id);

            // Resolve major name → major_id
            if (empty($data['major_id']) && !empty($data['major'])) {
                $major = Major::where('name', $data['major'])->first();
                if ($major) $data['major_id'] = $major->id;
            }
            if (empty($data['major_id'])) {
                $data['major_id'] = $assignment->major_id;
            }

            // Map 'year' → 'year_level'
            if (empty($data['year_level']) && !empty($data['year'])) {
                $data['year_level'] = $data['year'];
            }
            if (empty($data['year_level'])) {
                $data['year_level'] = $assignment->year_level;
            }

            if (empty($data['semester'])) {
                $data['semester'] = $assignment->semester;
            }

            // Resolve subject by name
            if (empty($data['subject_id']) && !empty($data['name'])) {
                $subject = Subject::firstOrCreate(
                    ['name' => $data['name']],
                    ['subject_Code' => $data['subject_Code'] ?? null]
                );
                $data['subject_id'] = $subject->id;
            }
            if (empty($data['subject_id'])) {
                $data['subject_id'] = $assignment->subject_id;
            }

            $validatedData = $this->validateAssignment($data, $assignment->id);
            
            // 3. Update and return
            $assignment->update($validatedData);
            
            // Refresh to get fresh relationship data
            return $assignment->load(['major', 'subject']);
            
            
        });
    }

    public function delete($id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $assignment = MajorSubject::find($id);
            
            if (!$assignment) {
                Log::warning('Curriculum assignment not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Curriculum assignment not found.");
            }
            
            return $assignment->delete();
            
            
        });
    }


    // validate 
    protected function validateAssignment(array $data, ?int $ignoreId = null): array
    {
        $rules = [
            'major_id'   => 'required|exists:majors,id',
            'year_level' => 'required|integer|between:1,5',
            'semester'   => 'required|integer|in:1,2',

            // CHANGE THIS: Change 'required' to 'nullable'
            'subject_id'   => 'nullable|exists:subjects,id',
            'subject_Code' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        $validator->after(function ($validator) use ($data, $ignoreId) {
            // Ensure we actually have a subject_id before checking for duplicates
            if (!isset($data['subject_id'])) {
                return;
            }

            $exists = MajorSubject::where('major_id', $data['major_id'])
                ->where('subject_id', $data['subject_id'])
                ->where('year_level', $data['year_level'])
                ->where('semester', $data['semester'])
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $validator->errors()->add('subject_id', 'This subject is already assigned to this slot.');
            }
        });

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('subject_id')
                ?: $validator->errors()->first('major_id')
                ?: $validator->errors()->first('year_level')
                ?: $validator->errors()->first('semester')
                ?: 'Validation failed for major-subject assignment.';

            Log::warning('Major-subject assignment validation failed.', [
                'ignore_id' => $ignoreId,
                'data' => [
                    'major_id'   => $data['major_id'] ?? null,
                    'subject_id' => $data['subject_id'] ?? null,
                    'year_level' => $data['year_level'] ?? null,
                    'semester'   => $data['semester'] ?? null,
                    'name'       => $data['name'] ?? null,
                ],
                'errors' => $errors,
            ]);

            throw new ApiException(ResponseStatus::EXISTING_DATA, $message, ['errors' => $validator->errors()]);
        }

        return $validator->validated();
    }

    private function findClass(int $classId): Classes
    {
        $class = Classes::find($classId);

        if (!$class) {
            Log::warning('Class not found for major-subject operation.', ['class_id' => $classId]);
            throw new ApiException(ResponseStatus::NOT_FOUND, 'Class not found.');
        }

        return $class;
    }
}




