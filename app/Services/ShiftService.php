<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\Shift;
use Illuminate\Validation\Rule;

class ShiftService extends BaseService
{
    public function index(): PaginatedResult
    {
        $query = Shift::query()->latest();

        return $this->paginateResponse($query, ShiftResource::class);
    }

    public function findById(int $id): Shift
    {
        $shift = Shift::find($id);

        // Validate exist id
        if (!$shift) {
            throw new ApiException(ResponseStatus::NOT_FOUND,"Shift id :$id not found.");
        }
        return $shift;
    }

    public function create(array $data): Shift
    {
        return Shift::create($this->validateExisting($data));
    }

    public function update(int $id, array $data): Shift
    {
        $shift = $this->findById($id);

        // Validate duplicate name
        $validatedData = $this->validateExisting($data, $shift->id);

        // Update the model in database
        $shift->update($validatedData);

        // Return the updated model
        return $shift;
    }

    public function delete(int $id): bool
    {
        $shift = $this->findById($id);
        return $shift->delete();
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name_kh' => [
                'required',
                'string',
                Rule::unique('shifts', 'name_kh')->ignore($ignoreId),
            ],
            'name_en' => [
                'required',
                'string',
                Rule::unique('shifts', 'name_en')->ignore($ignoreId),
            ],
            'time_range' => [
                'required',
                'string',
            ],
        ]);

        if ($validator->fails()) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                "Shift already exists.",
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}
