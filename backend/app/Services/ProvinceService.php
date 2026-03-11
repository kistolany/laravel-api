<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\ProvinceResource;
use App\Models\Province;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProvinceService extends BaseService
{
    public function index(): PaginatedResult
    {
        $query = Province::query()->latest()
            ->when(request('name'), fn($q, $term) => $q->where('name', 'like', "%{$term}%"));

        return $this->paginateResponse($query, ProvinceResource::class);
    }

    public function findById(int $id): Province
    {
        $province = Province::find($id);

        if (!$province) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Province not found.");
        }

        return $province;
    }

    public function create(array $data): Province
    {
        $validatedData = $this->validateExisting($data);
        return Province::create($validatedData);
    }

    public function update(int $id, array $data): Province
    {
        $province = $this->findById($id);
        $validatedData = $this->validateExisting($data, $province->id);
        $province->update($validatedData);
        return $province;
    }

    public function delete(int $id): bool
    {
        $province = $this->findById($id);
        return $province->delete();
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = Validator::make($data, [
            'name' => [
                'required',
                'string',
                Rule::unique('provinces', 'name')->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "The province name already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
