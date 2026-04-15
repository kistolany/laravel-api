<?php

namespace App\Services\Address;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Address\ProvinceResource;
use App\Models\Province;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class ProvinceService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = Province::query()->latest()
                ->when(request('name'), fn($q, $term) => $q->where('name', 'like', "%{$term}%"));
            
            return $this->paginateResponse($query, ProvinceResource::class);
            
            
        });
    }

    public function findById(int $id): Province
    {
        return $this->trace(__FUNCTION__, function () use ($id): Province {
            $province = Province::find($id);
            
            if (!$province) {
                Log::warning('Province not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Province not found.");
            }
            
            return $province;
            
            
        });
    }

    public function create(array $data): Province
    {
        return $this->trace(__FUNCTION__, function () use ($data): Province {
            $validatedData = $this->validateExisting($data);
            return Province::create($validatedData);
            
            
        });
    }

    public function update(int $id, array $data): Province
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Province {
            $province = $this->findById($id);
            $validatedData = $this->validateExisting($data, $province->id);
            $province->update($validatedData);
            return $province;
            
            
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $province = $this->findById($id);
            return $province->delete();
            
            
        });
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
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name')
                ?: 'Validation failed for province data.';

            Log::warning('Province validation failed.', [
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




