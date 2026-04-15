<?php

namespace App\Services\Address;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Address\CommuneResource;
use App\Models\Commune;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
class CommuneService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = Commune::query()->latest()
                ->when(request('district_id'), fn($q, $districtId) => $q->where('district_id', $districtId))
                ->when(request('name'), fn($q, $term) => $q->where('name', 'like', "%{$term}%"));
            
            return $this->paginateResponse($query, CommuneResource::class);
            
            
        });
    }

    public function findById(int $id): Commune
    {
        return $this->trace(__FUNCTION__, function () use ($id): Commune {
            $commune = Commune::find($id);
            
            if (!$commune) {
                Log::warning('Commune not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Commune not found.");
            }
            
            return $commune;
            
            
        });
    }

    public function create(array $data): Commune
    {
        return $this->trace(__FUNCTION__, function () use ($data): Commune {
            $validatedData = $this->validateExisting($data);
            return Commune::create($validatedData);
            
            
        });
    }

    public function update(int $id, array $data): Commune
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Commune {
            $commune = $this->findById($id);
            if (!isset($data['district_id'])) {
                $data['district_id'] = $commune->district_id;
                Log::info("Using existing district ID for commune: {$commune->id}");
            }
            $validatedData = $this->validateExisting($data, $commune->id);
            $commune->update($validatedData);
            return $commune;
            
            
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $commune = $this->findById($id);

            $deleted = $commune->delete();

            if (!$deleted) {
                Log::error('Failed to delete commune.', ['id' => $id]);
                return false;
            }

            Log::info('Commune deleted successfully.', ['id' => $id]);

            return true;
        });
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
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name')
                ?: $validator->errors()->first('district_id')
                ?: 'Validation failed for commune data.';

            Log::warning('Commune validation failed.', [
                'district_id' => $districtId,
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




