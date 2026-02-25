<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\FacultyResource;
use App\Models\Faculty;
use Illuminate\Validation\Rule;

class FacultyService extends BaseService
{
    public function index(): PaginatedResult
    {
        //  find snd sort by lstest 
        $query = Faculty::query()->latest()

            // handle on search by name
            ->when(request('name'), fn($q, $term) => $q->search($term));

        return $this->paginateResponse($query, FacultyResource::class);
    }

    public function findById(int $id): Faculty
    {

        // find id method
        $faculty = Faculty::find($id);

        // validate if id null throw error
        if (!$faculty) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Faculty not found.");
        }
        
        return $faculty;
    }

    public function create(array $data): Faculty
    {
        // call method and validate exist name 
        $validatedData = $this->validateExisting($data);

        //create and return
        return Faculty::create($validatedData);
    }

    public function update(int $id, array $data): Faculty
    {
        // 1. Check if ID exists (Throws ApiException if not)
        $faculty = $this->findById($id);

        // 2. Validate data (Ignoring the current ID for unique checks)
        $validatedData = $this->validateExisting($data, $faculty->id);

        // 3. Perform update
        $faculty->update($validatedData);
        return $faculty;
    }

    public function delete($id): bool
    {
        // check this id before delete
        $faculty = $this->findById($id);

        // perform delete
        return $faculty->delete($faculty);
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
        ]);

        if ($validator->fails()) {

            // call api exception for throw
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "The faculty name already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
