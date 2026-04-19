<?php

namespace App\Services\Major;

use App\Services\BaseService;
use App\Services\Faculty\FacultyService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Major\MajorResource;
use App\Models\Faculty;
use App\Models\Major;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class MajorService extends BaseService
{

    // Call faculty for validate
    public function __construct(
        protected FacultyService $facultyService
    ) {
                    
    }

    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            // 1. Start the query on the Subject model 
            $query = Major::query()->latest();
            
            // 2. Handle search: if 'name' OR 'code' is provided, apply the search scope
            $query->when(request('name'), fn($q, $term) => $q->search($term));
            
            // search by faculty_id 
            $query->when(request('faculty_id'), fn($q, $id) => $q->where('faculty_id', $id));
            return $this->paginateResponse($query, MajorResource::class);
            
            
        });
    }

    public function getByFaculty(int $facultyId)
    {
        return $this->trace(__FUNCTION__, function () use ($facultyId) {
            // Validate faculty exists
            $this->facultyService->findById($facultyId);
            
            return Major::where('faculty_id', $facultyId)
                ->orderBy('name')
                ->get();
            
            
        });
    }

    public function findById(int $id): Major
    {
        return $this->trace(__FUNCTION__, function () use ($id): Major {
            // find id method
            $subject = Major::find($id);
            
            // validate if id null throw error
            if (!$subject) {
                Log::warning('Major not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Major not found.");
            }
            
            return $subject;
            
            
        });
    }

    public function create(array $data): Major
    {
        return $this->trace(__FUNCTION__, function () use ($data): Major {
            // Resolve faculty name → faculty_id if frontend sends name string
            if (empty($data['faculty_id']) && !empty($data['faculty'])) {
                $faculty = Faculty::where('name', $data['faculty'])->first();
                if (!$faculty) {
                    throw new ApiException(ResponseStatus::NOT_FOUND, "Faculty '{$data['faculty']}' not found.");
                }
                $data['faculty_id'] = $faculty->id;
            }

            $this->facultyService->findById($data['faculty_id']);
            $validatedData = $this->validateExisting($data);
            $validatedData['faculty_id'] = $data['faculty_id'];
            return Major::create($validatedData);
        });
    }

    public function update(int $id, array $data): Major
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Major {
            $major = $this->findById($id);

            // Resolve faculty name → faculty_id
            if (empty($data['faculty_id']) && !empty($data['faculty'])) {
                $faculty = Faculty::where('name', $data['faculty'])->first();
                if ($faculty) {
                    $data['faculty_id'] = $faculty->id;
                }
            }

            if (!isset($data['faculty_id'])) {
                $data['faculty_id'] = $major->faculty_id;
            } else {
                $this->facultyService->findById($data['faculty_id']);
            }

            $validatedData = $this->validateExisting($data, $major->id);
            $major->update($validatedData);
            return $major;
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

    // validate method
    protected function validateExisting(array $data, ?int $ignoreId = null): array
    {
        $facultyId = $data['faculty_id'] ?? null;

        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'name' => [
                'required',
                'string',
                Rule::unique('majors', 'name')
                    ->where('faculty_id', $facultyId)
                    ->ignore($ignoreId),
            ],
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first('name') ?: 'Validation failed for major data.';

            Log::warning('Major validation failed.', [
                'faculty_id' => $facultyId,
                'ignore_id'  => $ignoreId,
                'name'       => $data['name'] ?? null,
                'errors'     => $validator->errors()->toArray(),
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




