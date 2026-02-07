<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\MajorSubjectResource;
use App\Models\MajorSubject;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class Major_subject_service extends BaseService
{


    public function index(): PaginatedResult
    {
        // Start query on the junction table
        $query = MajorSubject::query()->with(['major', 'subject'])->latest();

        // Handle filters: Allow Admin to select by Major, Year, or Semester
        $query->when(request('major_id'), fn($q, $id) => $q->where('major_id', $id))
            ->when(request('year_level'), fn($q, $year) => $q->where('year_level', $year))
            ->when(request('semester'), fn($q, $sem) => $q->where('semester', $sem));

        return $this->paginateResponse($query, MajorSubjectResource::class);
    }



    public function findById(int $id): MajorSubject
    {
        $assignment = MajorSubject::with(['major', 'subject'])->find($id);

        if (!$assignment) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Assignment not found.");
        }

        return $assignment;
    }



    public function create(array $data): MajorSubject
    {
        $validatedData = $this->validateAssignment($data);

        return MajorSubject::create($validatedData);
    }



    public function update(int $id, array $data): MajorSubject
    {
        // 1. Find it
        $assignment = $this->findById($id);

        // 2. Validate (Ignoring the current ID to avoid "duplicate" errors with itself)
        $validatedData = $this->validateAssignment($data, $assignment->id);

        // 3. Update and return
        $assignment->update($validatedData);
        
        // Refresh to get fresh relationship data
        return $assignment->load(['major', 'subject']);
    }



    public function delete($id): bool
    {
        $assignment = MajorSubject::find($id);

        if (!$assignment) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Curriculum assignment not found.");
        }

        return $assignment->delete();
    }

    
    // validate 
    protected function validateAssignment(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            'major_id'   => 'required|exists:majors,id',
            'subject_id' => [
                'required',
                'exists:subjects,id',
                // PREVENT DUPLICATE: Same subject cannot be in same major/year/sem twice
                Rule::unique('major_subjects')->where(function ($query) use ($data) {
                    return $query->where('major_id', $data['major_id'] ?? null)
                        ->where('year_level', $data['year_level'] ?? null)
                        ->where('semester', $data['semester'] ?? null);
                })->ignore($ignoreId),
            ],
            'year_level' => 'required|integer|between:1,4',
            'semester'   => 'required|integer|in:1,2',
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "This subject is already assigned to this semester in this major.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
