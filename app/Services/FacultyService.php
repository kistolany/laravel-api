<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\FacultyResource;
use App\Models\Faculty;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Exception;

class FacultyService extends BaseService
{



    public function index(): PaginatedResult
    {
        $query = Faculty::query()->latest()

            // call nmethod for search 
            ->when(request('name'), fn($q, $term) => $q->search($term));

        // return page
        return $this->paginateResponse($query, FacultyResource::class);
    }


    public function create(array $data): Faculty
    {
        // Call the separate validation method
        $validate = $this->validateExisting($data);

        return Faculty::create( $validate);
    }


    function update(Faculty $faculty, array $data): Faculty
    {
        $faculty->update($data);
        return $faculty;
    }


    public function delete(Faculty $faculty): bool
    {
        return $faculty->delete();
    }


    /**
     * Validate Faculty data manually existing name 
     */
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
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
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "The faculty name already exists.",
                data: ['errors' => $validator->errors()]);
        }

        return $validator->validated();
    }
}
