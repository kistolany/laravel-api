<?php

namespace App\Services\Subject;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Subject\SubjectResource;
use App\Models\Subject;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class SubjectService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            // 1. Start the query on the Subject model 
            $query = Subject::query()->latest();
            
            // 2. Handle search: if 'name' OR 'code' is provided, apply the search scope
            $query->when(request('name'), fn($q, $term) => $q->search($term))
                ->when(request('code'), fn($q, $term) => $q->search($term));
            
            return $this->paginateResponse($query, SubjectResource::class);
            
            
        });
    }

    public function findById(int $id): Subject
    {
        return $this->trace(__FUNCTION__, function () use ($id): Subject {
            // find id method
            $subject = Subject::find($id);
            
            // validate if id null throw error
            if (!$subject) {
                Log::warning('Subject not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Subject not found.");
            }
            
            return $subject;
            
            
        });
    }

    public function create(array $data): Subject
    {
        return $this->trace(__FUNCTION__, function () use ($data): Subject {
            // call method and validate exist name 
            $validatedData = $this->validateExisting($data);
            
            //create and return
            return Subject::create($validatedData);
            
            
        });
    }

    public function update(int $id, array $data): Subject
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Subject {
            // 1. Check if ID exists (Throws ApiException if not)
            $subject = $this->findById($id);
            
            // 2. Validate data (Ignoring the current ID for unique checks)
            $validatedData = $this->validateExisting($data, $subject->id);
            
            // 3. Perform update
            $subject->update($validatedData);
            return $subject;
            
            
        });
    }

    public function delete($id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            // check this id before delete
            $subject = $this->findById($id);
            
            // perform delete
            return $subject->delete($subject);
            
            
        });
    }

    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $rules = [
            'name_kh' => [
                'string',
                Rule::unique('faculties', 'name_kh')->ignore($ignoreId),
            ],
            'name_eg' => [
                'required',
                'string',
                Rule::unique('faculties', 'name_eg')->ignore($ignoreId),
            ],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($data, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $message = $validator->errors()->first('name_eg')
                ?: $validator->errors()->first('name_kh')
                ?: 'Validation failed for subject data.';

            Log::warning('Subject validation failed.', [
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



