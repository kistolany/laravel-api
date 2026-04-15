<?php

namespace App\Services\Major;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Major\MajorSubjectResource;
use App\Models\Classes;
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
            
            return MajorSubject::with(['major', 'subject'])
                ->where('major_id', $class->major_id)
                ->where('year_level', $class->year_level)
                ->where('semester', $class->semester)
                ->orderBy('subject_id')
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
            
                // 1. If subject_id isn't sent, find or create by English Name
                if (empty($data['subject_id'])) {
                    $subject = Subject::firstOrCreate(
                        ['name_eg' => $data['name_eg']], // Search criteria
                        [
                            'subject_Code' => $data['subject_Code'] ?? null, // Optional
                            'name_kh'      => $data['name_kh'] ?? null,      // Optional
                        ]
                    );
            
                    $data['subject_id'] = $subject->id;
                }
            
                // 2. Validate the assignment link
                $validatedData = $this->validateAssignment($data);
            
                // 3. Create the record in major_subjects
                return MajorSubject::create($validatedData);
            });
            
            
        });
    }

    public function createFromClass(int $classId, array $data): MajorSubject
    {
        return $this->trace(__FUNCTION__, function () use ($classId, $data): MajorSubject {
            $class = $this->findClass($classId);
            
            return $this->create(array_merge($data, [
                'major_id' => $class->major_id,
                'year_level' => $class->year_level,
                'semester' => $class->semester,
            ]));
            
            
        });
    }

    public function update(int $id, array $data): MajorSubject
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): MajorSubject {
            // 1. Find it
            $assignment = $this->findById($id);
            
            // 2. Validate (Ignoring the current ID to avoid "duplicate" errors with itself)
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
            'subject_id' => 'nullable|exists:subjects,id',

            'name_eg'      => 'required_without:subject_id|string',
            'subject_Code' => 'nullable|string',
            'name_kh'      => 'nullable|string',
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
                    'major_id' => $data['major_id'] ?? null,
                    'subject_id' => $data['subject_id'] ?? null,
                    'year_level' => $data['year_level'] ?? null,
                    'semester' => $data['semester'] ?? null,
                    'name_eg' => $data['name_eg'] ?? null,
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




