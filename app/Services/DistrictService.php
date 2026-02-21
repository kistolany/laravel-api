<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\DistrictResource;
use App\Models\District;
use Illuminate\Validation\Rule;

class DistrictService extends BaseService
{
    /**
     * List districts with pagination + optional search + filter by province
     */
    public function index(): PaginatedResult
    {
        $query = District::with('province')->latest()
            ->search(request('name'))             // use model search scope
            ->byProvince(request('province_id')); // filter by province

        return $this->paginateResponse($query, DistrictResource::class);
        
    }

    /**
     * Find a district by ID safely
     */
    public function findById(int $id): District
    {
        $district = District::find($id);

        if (!$district) {
            throw new ApiException(
                ResponseStatus::NOT_FOUND,
                "District id {$id} not found."
            );
        }

        return $district;
    }

    /**
     * Create a new district
     */
    public function create(array $data): District
    {
        // Validate duplicates and required fields
        $validated = $this->validateExisting($data);

        return District::create($validated);
    }

    /**
     * Update an existing district safely
     */
    public function update(int $id, array $data): District
    {
        $district = $this->findById($id);

        $validated = $this->validateExisting($data, $district->id);

        $district->update($validated);

        return $district;
    }

    /**
     * Delete a district
     */
    public function delete(int $id): bool
    {
        $district = $this->findById($id);

        return $district->delete();
    }

    /**
     * Validate duplicate district names and required fields
     */
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'province_id' => [
                'required',
                'exists:provinces,id'
            ],
            'name_kh' => [
                'required',
                'string',
                Rule::unique('districts', 'name_kh')->ignore($ignoreId),
            ],
            'name_en' => [
                'required',
                'string',
                Rule::unique('districts', 'name_en')->ignore($ignoreId),
            ],
            
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "District already exists or invalid data.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }

    /**
     * List districts by province for API convenience
     */
    public function listByProvince(int $provinceId)
    {
        $districts = District::where('province_id', $provinceId)
            ->orderBy('name_en')
            ->get();

        return DistrictResource::collection($districts)->resolve();
    }
}
