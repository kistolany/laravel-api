<?php

namespace App\Services\Shift;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Shift\ShiftResource;
use App\Models\Shift;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class ShiftService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = Shift::query()->latest();
            
            return $this->paginateResponse($query, ShiftResource::class);
            
            
        });
    }

    public function findById(int $id): Shift
    {
        return $this->trace(__FUNCTION__, function () use ($id): Shift {
            $shift = Shift::find($id);
            
            // Validate exist id
            if (!$shift) {
                Log::warning('Shift not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND,"Shift id :$id not found.");
            }
            return $shift;
            
            
        });
    }

    public function create(array $data): Shift
    {
        return $this->trace(__FUNCTION__, function () use ($data): Shift {
            return Shift::create($this->validateExisting($data));
            
            
        });
    }

    public function update(int $id, array $data): Shift
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Shift {
            $shift = $this->findById($id);
            
            // Validate duplicate name
            $validatedData = $this->validateExisting($data, $shift->id);
            
            // Update the model in database
            $shift->update($validatedData);
            
            // Return the updated model
            return $shift;
            
            
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $shift = $this->findById($id);
            return $shift->delete();
            
            
        });
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => [
                'required',
                'string',
                Rule::unique('shifts', 'name')->ignore($ignoreId),
            ],
            'time_range' => [
                'required',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name')
                ?: $validator->errors()->first('time_range')
                ?: 'Validation failed for shift data.';

            Log::warning('Shift validation failed.', [
                'ignore_id' => $ignoreId,
                'name' => $data['name'] ?? null,
                'time_range' => $data['time_range'] ?? null,
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




