<?php

namespace App\Services\Faculty;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Faculty\FacultyResource;
use App\Models\Faculty;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class FacultyService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            //  find snd sort by lstest 
            $query = Faculty::query()->latest()
            
                // handle on search by name
                ->when(request('name'), fn($q, $term) => $q->search($term));
            
            return $this->paginateResponse($query, FacultyResource::class);
            
            
        });
    }

    public function findById(int $id): Faculty
    {
        return $this->trace(__FUNCTION__, function () use ($id): Faculty {
            // find id method
            $faculty = Faculty::find($id);
            
            // validate if id null throw error
            if (!$faculty) {
                Log::warning('Faculty not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Faculty not found.");
            }
            
            return $faculty;
            
            
        });
    }

    public function create(array $data): Faculty
    {
        return $this->trace(__FUNCTION__, function () use ($data): Faculty {
            // call method and validate exist name 
            $validatedData = $this->validateExisting($data);
            
            //create and return
            return Faculty::create($validatedData);
            
            
        });
    }

    public function update(int $id, array $data): Faculty
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Faculty {
            // 1. Check if ID exists (Throws ApiException if not)
            $faculty = $this->findById($id);
            
            // 2. Validate data (Ignoring the current ID for unique checks)
            $validatedData = $this->validateExisting($data, $faculty->id);
            
            // 3. Perform update
            $faculty->update($validatedData);
            return $faculty;
            
            
        });
    }

    public function delete($id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            // check this id before delete
            $faculty = $this->findById($id);
            
            // perform delete
            return $faculty->delete($faculty);
            
            
        });
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name_kh' => [
                'nullable',
                'string',
                Rule::unique('faculties', 'name_kh')->ignore($ignoreId),
            ],
            'name_eg' => [
                'required',
                'string',
                Rule::unique('faculties', 'name_eg')->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name_eg')
                ?: $validator->errors()->first('name_kh')
                ?: 'Validation failed for faculty data.';

            Log::warning('Faculty validation failed.', [
                'ignore_id' => $ignoreId,
                'name_eg' => $data['name_eg'] ?? null,
                'name_kh' => $data['name_kh'] ?? null,
                'errors' => $errors,
            ]);

            // call api exception for throw
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                $message,
                data: ['errors' => $validator->errors()]
            );
        }

        return $validator->validated();
    }
}




