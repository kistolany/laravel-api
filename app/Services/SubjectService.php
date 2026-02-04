<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\SubjectResource;
use App\Models\Subject;
use Illuminate\Validation\Rule;

class SubjectService extends BaseService
{
    public function index(): PaginatedResult
    {
        // 1. Start the query on the Subject model 
        $query = Subject::query()->latest();

        // 2. Handle search: if 'name' OR 'code' is provided, apply the search scope
        $query->when(request('name'), fn($q, $term) => $q->search($term))
            ->when(request('code'), fn($q, $term) => $q->search($term));

        return $this->paginateResponse($query, SubjectResource::class);
    }

    public function findById(int $id): Subject
    {

        // find id method
        $subject = Subject::find($id);

        // validate if id null throw error
        if (!$subject) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Subject not found.");
        }

        return $subject;
    }

    public function create(array $data): Subject
    {
        // call method and validate exist name 
        $validatedData = $this->validateExisting($data);

        //create and return
        return Subject::create($validatedData);
    }

    public function update(int $id, array $data): Subject
    {
        // 1. Check if ID exists (Throws ApiException if not)
        $subject = $this->findById($id);

        // 2. Validate data (Ignoring the current ID for unique checks)
        $validatedData = $this->validateExisting($data, $subject->id);

        // 3. Perform update
        $subject->update($validatedData);
        return $subject;
    }

    public function delete($id): bool
    {
        // check this id before delete
        $subject = $this->findById($id);

        // perform delete
        return $subject->delete($subject);
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name_kh' => [
                'required',
                'string',
                Rule::unique('faculties', 'name_kh')->ignore($ignoreId),
            ],
            'name_eg' => [
                'required',
                'string',
                Rule::unique('faculties', 'name_eg')->ignore($ignoreId),
            ],
             'subject_Code' => [
                'required',
                'string',
                Rule::unique('subjects', 'subject_Code')->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {

            // call api exception for throw
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "The Subject name already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
