<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\CommuneResource;
use App\Models\Commune;
use Illuminate\Validation\Rule;

class CommuneService extends BaseService
{
    /**
     * List communes with pagination + optional search.
     */
    public function index(): PaginatedResult
    {
        $query = Commune::query()->latest();

        // optional search
        $query->when(
            request('name'),
            fn ($q, $term) =>
                $q->where('name_kh', 'like', "%{$term}%")
                  ->orWhere('name_en', 'like', "%{$term}%")
        );

        return $this->paginateResponse($query, CommuneResource::class);
    }

    /**
     * Find commune safely.
     */
    public function findById(int $id): Commune
    {
        $commune = Commune::find($id);

        if (!$commune) {
            throw new ApiException(
                ResponseStatus::NOT_FOUND,
                "Commune id {$id} not found."
            );
        }

        return $commune;
    }

    /**
     * Create commune.
     */
    public function create(array $data): Commune
    {
        return Commune::create(
            $this->validateExisting($data)
        );
    }

    /**
     * Update commune.
     */
    public function update(int $id, array $data): Commune
    {
        $commune = $this->findById($id);

        $validated = $this->validateExisting($data, $commune->id);

        $commune->update($validated);

        return $commune;
    }

    /**
     * Delete commune.
     */
    public function delete(int $id): bool
    {
        $commune = $this->findById($id);

        return $commune->delete();
    }

    /**
     * Validate duplicate commune.
     */
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name_kh' => [
                'nullable',
                'string',
                Rule::unique('communes', 'name_kh')->ignore($ignoreId),
            ],
            'name_en' => [
                'required',
                'string',
                Rule::unique('communes', 'name_en')->ignore($ignoreId),
            ],
            'district_id' => [
                'required',
                'exists:districts,id',
            ],
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Commune already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
