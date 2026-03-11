<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\CommuneResource;
use App\Models\Commune;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CommuneService extends BaseService
{
    public function index(): PaginatedResult
    {
        $query = Commune::query()->latest()
            ->when(request('district_id'), fn($q, $districtId) => $q->where('district_id', $districtId))
            ->when(request('name'), fn($q, $term) => $q->where('name', 'like', "%{$term}%"));

        return $this->paginateResponse($query, CommuneResource::class);
    }

    public function findById(int $id): Commune
    {
        $commune = Commune::find($id);

        if (!$commune) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Commune not found.");
        }

        return $commune;
    }

    public function create(array $data): Commune
    {
        $validatedData = $this->validateExisting($data);
        return Commune::create($validatedData);
    }

    public function update(int $id, array $data): Commune
    {
        $commune = $this->findById($id);
        if (!isset($data['district_id'])) {
            $data['district_id'] = $commune->district_id;
        }
        $validatedData = $this->validateExisting($data, $commune->id);
        $commune->update($validatedData);
        return $commune;
    }

    public function delete(int $id): bool
    {
        $commune = $this->findById($id);
        return $commune->delete();
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $districtId = $data['district_id'] ?? null;

        $validator = Validator::make($data, [
            'district_id' => 'required|exists:districts,id',
            'name' => [
                'required',
                'string',
                Rule::unique('communes', 'name')
                    ->where(fn($q) => $q->where('district_id', $districtId))
                    ->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "The commune name already exists in this district.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
