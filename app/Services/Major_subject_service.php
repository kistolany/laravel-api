<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\MajorSubjectResource;
use App\Models\MajorSubject;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
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
        // find id method with relationships
        $assignment = MajorSubject::with(['major', 'subject'])->find($id);

        // Validate exist id
        if (!$assignment) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Assignment not found.");
        }

        return $assignment;
    }


    public function create(array $data): MajorSubject
    {
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
            throw new ApiException(ResponseStatus::EXISTING_DATA, "Validation Error", ['errors' => $validator->errors()]);
        }

        return $validator->validated();
    }
}
