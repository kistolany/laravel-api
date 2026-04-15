<?php

namespace App\Services\Scholarship;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Scholarship\ScholarshipResource;
use App\Models\Scholarship;
use Illuminate\Support\Facades\Log;
class ScholarshipService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = Scholarship::with(['student.parentGuardian'])->latest()
                ->when(request('student_id'), fn($q, $id) => $q->where('student_id', $id))
                ->when(request('search'), function ($q, $search) {
                    $q->whereHas('student', function ($sub) use ($search) {
                        $sub->where('full_name_en', 'like', "%{$search}%")
                            ->orWhere('full_name_kh', 'like', "%{$search}%");
                    });
                });
            
            return $this->paginateResponse($query, ScholarshipResource::class);
            
            
        });
    }

    public function findById(int $id): Scholarship
    {
        return $this->trace(__FUNCTION__, function () use ($id): Scholarship {
            $scholarship = Scholarship::with(['student.parentGuardian'])->find($id);
            
            if (!$scholarship) {
                Log::warning('Scholarship not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Scholarship with ID :$id not found.");
            }
            
            return $scholarship;
            
            
        });
    }

    public function create(array $data): Scholarship
    {
        return $this->trace(__FUNCTION__, function () use ($data): Scholarship {
            return Scholarship::create($data);
            
            
        });
    }

    public function update(int $id, array $data): Scholarship
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): Scholarship {
            $scholarship = $this->findById($id);
            $scholarship->update($data);
            return $scholarship;
            
            
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $scholarship = $this->findById($id);
            return $scholarship->delete();
            
            
        });
    }
}




