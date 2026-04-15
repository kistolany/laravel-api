<?php

namespace App\Services\Address;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Address\DistrictResource;
use App\Models\District;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class DistrictService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = District::query()->latest()
                ->when(request('province_id'), fn($q, $provinceId) => $q->where('province_id', $provinceId))
                ->when(request('name'), fn($q, $term) => $q->where('name', 'like', "%{$term}%"));
            
            return $this->paginateResponse($query, DistrictResource::class);
            
            
        });
    }

    public function findById(int $id): District
    {
        return $this->trace(__FUNCTION__, function () use ($id): District {
            $district = District::find($id);
            
            if (!$district) {
                Log::warning('District not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "District not found.");
            }
            
            return $district;
            
            
        });
    }

    public function create(array $data): District
    {
        return $this->trace(__FUNCTION__, function () use ($data): District {
            $validatedData = $this->validateExisting($data);
            return District::create($validatedData);
            
            
        });
    }

    public function update(int $id, array $data): District
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): District {
            $district = $this->findById($id);
            if (!isset($data['province_id'])) {
                $data['province_id'] = $district->province_id;
            }
            $validatedData = $this->validateExisting($data, $district->id);
            $district->update($validatedData);
            return $district;
            
            
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $district = $this->findById($id);
            return $district->delete();
            
            
        });
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $provinceId = $data['province_id'] ?? null;

        $validator = Validator::make($data, [
            'province_id' => 'required|exists:provinces,id',
            'name' => [
                'required',
                'string',
                Rule::unique('districts', 'name')
                    ->where(fn($q) => $q->where('province_id', $provinceId))
                    ->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name')
                ?: $validator->errors()->first('province_id')
                ?: 'Validation failed for district data.';

            Log::warning('District validation failed.', [
                'province_id' => $provinceId,
                'name' => $data['name'] ?? null,
                'ignore_id' => $ignoreId,
                'errors' => $errors,
            ]);

            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}




