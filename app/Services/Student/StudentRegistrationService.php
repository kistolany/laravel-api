<?php

namespace App\Services\Student;

use App\Services\BaseService;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\Student\StudentRegistrationResource;
use App\Models\StudentRegistration;
use Illuminate\Support\Facades\Log;
class StudentRegistrationService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = StudentRegistration::with('student')->latest()
                ->when(request('student_id'), fn($q, $id) => $q->where('student_id', $id))
                ->when(request('search'), function ($q, $search) {
                    $q->whereHas('student', function ($sub) use ($search) {
                        $sub->where('full_name_en', 'like', "%{$search}%")
                            ->orWhere('full_name_kh', 'like', "%{$search}%");
                    });
                });
            
            return $this->paginateResponse($query, StudentRegistrationResource::class);
            
            
        });
    }

    public function findById(int $id): StudentRegistration
    {
        return $this->trace(__FUNCTION__, function () use ($id): StudentRegistration {
            $registration = StudentRegistration::with('student')->find($id);
            
            if (!$registration) {
                Log::warning('Student registration not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Student Registration with ID :$id not found.");
            }
            
            return $registration;
            
            
        });
    }

    public function create(array $data): StudentRegistration
    {
        return $this->trace(__FUNCTION__, function () use ($data): StudentRegistration {
            return StudentRegistration::create($data);
            
            
        });
    }

    public function update(int $id, array $data): StudentRegistration
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): StudentRegistration {
            $registration = $this->findById($id);
            $registration->update($data);
            return $registration;
            
            
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            $registration = $this->findById($id);
            return $registration->delete();
            
            
        });
    }
}




