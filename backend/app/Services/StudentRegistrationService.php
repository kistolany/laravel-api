<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\StudentRegistrationResource;
use App\Models\StudentRegistration;

class StudentRegistrationService extends BaseService
{
    public function index(): PaginatedResult
    {
        $query = StudentRegistration::with('student')->latest()
            ->when(request('student_id'), fn($q, $id) => $q->where('student_id', $id))
            ->when(request('search'), function ($q, $search) {
                $q->whereHas('student', function ($sub) use ($search) {
                    $sub->where('full_name_en', 'like', "%{$search}%")
                        ->orWhere('full_name_kh', 'like', "%{$search}%");
                });
            });

        return $this->paginateResponse($query, StudentRegistrationResource::class);
    }

    public function findById(int $id): StudentRegistration
    {
        $registration = StudentRegistration::with('student')->find($id);

        if (!$registration) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Student Registration with ID :$id not found.");
        }

        return $registration;
    }

    public function create(array $data): StudentRegistration
    {
        return StudentRegistration::create($data);
    }

    public function update(int $id, array $data): StudentRegistration
    {
        $registration = $this->findById($id);
        $registration->update($data);
        return $registration;
    }

    public function delete(int $id): bool
    {
        $registration = $this->findById($id);
        return $registration->delete();
    }
}
