<?php

namespace App\Services;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\ScholarshipResource;
use App\Models\Scholarship;

class ScholarshipService extends BaseService
{
    public function index(): PaginatedResult
    {
        $query = Scholarship::with(['student.parentGuardian'])->latest()
            ->when(request('student_id'), fn($q, $id) => $q->where('student_id', $id))
            ->when(request('search'), function ($q, $search) {
                $q->whereHas('student', function ($sub) use ($search) {
                    $sub->where('full_name_en', 'like', "%{$search}%")
                        ->orWhere('full_name_kh', 'like', "%{$search}%");
                });
            });

        return $this->paginateResponse($query, ScholarshipResource::class);
    }

    public function findById(int $id): Scholarship
    {
        $scholarship = Scholarship::with(['student.parentGuardian'])->find($id);

        if (!$scholarship) {
            throw new ApiException(ResponseStatus::NOT_FOUND, "Scholarship with ID :$id not found.");
        }

        return $scholarship;
    }

    public function create(array $data): Scholarship
    {
        return Scholarship::create($data);
    }

    public function update(int $id, array $data): Scholarship
    {
        $scholarship = $this->findById($id);
        $scholarship->update($data);
        return $scholarship;
    }

    public function delete(int $id): bool
    {
        $scholarship = $this->findById($id);
        return $scholarship->delete();
    }
}
