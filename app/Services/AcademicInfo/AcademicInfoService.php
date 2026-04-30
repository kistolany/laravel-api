<?php

namespace App\Services\AcademicInfo;

use App\DTOs\PaginatedResult;
use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Http\Resources\AcademicInfo\AcademicInfoResource;
use App\Models\AcademicInfo;
use App\Services\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AcademicInfoService extends BaseService
{
    public function index(): PaginatedResult
    {
        return $this->trace(__FUNCTION__, function (): PaginatedResult {
            $query = AcademicInfo::with(['major.faculty', 'shift'])
                ->when(request('student_id'), fn ($query, $studentId) => $query->where('student_id', $studentId))
                ->latest();

            return $this->paginateResponse($query, AcademicInfoResource::class);
        });
    }

    public function byMajor(int $majorId): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($majorId): Collection {
            return AcademicInfo::with(['major.faculty', 'shift'])
                ->where('major_id', $majorId)
                ->get()
                ->values();
        });
    }

    public function byShift(int $shiftId): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($shiftId): Collection {
            return AcademicInfo::with(['major.faculty', 'shift'])
                ->where('shift_id', $shiftId)
                ->get()
                ->values();
        });
    }

    public function findById(int $id): AcademicInfo
    {
        return $this->trace(__FUNCTION__, function () use ($id): AcademicInfo {
            $academicInfo = AcademicInfo::with(['major.faculty', 'shift'])->find($id);

            if (!$academicInfo) {
                Log::warning('Academic info not found.', ['id' => $id]);
                throw new ApiException(ResponseStatus::NOT_FOUND, "Academic info with ID :$id not found.");
            }

            return $academicInfo;
        });
    }

    public function create(array $data): AcademicInfo
    {
        return $this->trace(__FUNCTION__, function () use ($data): AcademicInfo {
            return AcademicInfo::create($data)->load(['major.faculty', 'shift']);
        });
    }

    public function update(int $id, array $data): AcademicInfo
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): AcademicInfo {
            $academicInfo = $this->findById($id);
            $academicInfo->update($data);

            return $academicInfo->refresh()->load(['major.faculty', 'shift']);
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            return $this->findById($id)->delete();
        });
    }
}
