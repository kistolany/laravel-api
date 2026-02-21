<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\ProvinceResource;
use App\Models\Province;
use Illuminate\Validation\Rule;

class ProvinceService extends BaseService
{
    /**
     * List provinces with pagination + optional search.
     */
    public function index(): PaginatedResult
    {
        // 1. Start province query ordered by latest
        $query = Province::query()->latest();

        // 2. Optional search by name
        $query->when(
            request('name'),
            fn ($q, $term) => $q->where('name', 'like', "%{$term}%")
        );

        return $this->paginateResponse($query, ProvinceResource::class);
    }

    /**
     * Find province by ID with safety check.
     */
    public function findById(int $id): Province
    {
        $province = Province::find($id);

        if (!$province) {
            throw new ApiException(
                ResponseStatus::NOT_FOUND,"Province id {$id} not found.");
        }

        return $province;
    }

    /**
     * Create province with duplicate validation.
     */
    public function create(array $data): Province
    {
        return Province::create($this->validateExisting($data));
    }

    /**
     * Update province safely.
     */
    public function update(int $id, array $data): Province
    {
        //UnDone
        $province = $this->findById($id);

        $validated = $this->validateExisting($data, $province->id);

        $province->update($validated);

        return $province;
    }

    /**
     * Delete province after existence check.
     */
    public function delete(int $id): bool
    {
        $province = $this->findById($id);

        return $province->delete();
    }

    /**
     * Validate duplicate province name.
     */
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name_kh' => [
                'nullable',
                'string',
                Rule::unique('provinces', 'name_kh')->ignore($ignoreId),
            ],
            'name_en' => [
                'required',
                'string',
                Rule::unique('provinces', 'name_en')->ignore($ignoreId),
            ], 
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Province already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
