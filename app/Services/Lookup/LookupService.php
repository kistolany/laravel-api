<?php

namespace App\Services\Lookup;

use App\Services\BaseService;

use App\Models\Classes;
use App\Models\Commune;
use App\Models\District;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Province;
use App\Models\Shift;
use App\Models\Subject;
use Illuminate\Support\Facades\Cache;
class LookupService extends BaseService
{
    /**
     * Get all faculties for the top-level dropdown.
     */
    public function getFaculties()
    {
        return $this->trace(__FUNCTION__, function () {
            return $this->rememberLookup(__FUNCTION__, [], fn () => Faculty::select('id', 'name_eg', 'name_kh')
                ->orderBy('name_eg')
                ->get());
            
            
        });
    }

    /**
     * Get majors for a specific faculty, or all majors when no faculty is selected.
     */
    public function getMajorsByFaculty(mixed $facultyId = null)
    {
        return $this->trace(__FUNCTION__, function () use ($facultyId) {
            $facultyId = $this->toNullableInt($facultyId);
            
            return $this->rememberLookup(__FUNCTION__, ['faculty_id' => $facultyId], fn () => Major::query()
                ->when(!is_null($facultyId), fn ($query) => $query->where('faculty_id', $facultyId))
                ->select('id', 'name_eg', 'name_kh')
                ->orderBy('name_eg')
                ->get());
            
            
        });
    }

    public function getProvinces()
    {
        return $this->trace(__FUNCTION__, function () {
            return $this->rememberLookup(__FUNCTION__, [], fn () => Province::select('id', 'name')
                ->orderBy('name')
                ->get());
            
            
        });
    }

    public function getDistrictsByProvince(mixed $provinceId = null)
    {
        return $this->trace(__FUNCTION__, function () use ($provinceId) {
            $provinceId = $this->toNullableInt($provinceId);
            
            if ($provinceId === null) {
                return collect();
            }
            
            return $this->rememberLookup(__FUNCTION__, ['province_id' => $provinceId], fn () => District::where('province_id', $provinceId)
                ->select('id', 'name', 'province_id')
                ->orderBy('name')
                ->get());
            
            
        });
    }

    public function getCommunesByDistrict(mixed $districtId = null)
    {
        return $this->trace(__FUNCTION__, function () use ($districtId) {
            $districtId = $this->toNullableInt($districtId);
            
            if ($districtId === null) {
                return collect();
            }
            
            return $this->rememberLookup(__FUNCTION__, ['district_id' => $districtId], fn () => Commune::where('district_id', $districtId)
                ->select('id', 'name', 'district_id')
                ->orderBy('name')
                ->get());
            
            
        });
    }

    public function getSubjectsByMajor(mixed $majorId = null)
    {
        return $this->trace(__FUNCTION__, function () use ($majorId) {
            $majorId = $this->toNullableInt($majorId);
            
            return $this->rememberLookup(__FUNCTION__, ['major_id' => $majorId], fn () => Subject::query()
                ->when(!is_null($majorId), function ($query) use ($majorId) {
                    $query->whereIn(
                        'id',
                        MajorSubject::query()
                            ->where('major_id', $majorId)
                            ->select('subject_id')
                    );
                })
                ->select('id', 'subject_Code', 'name_eg', 'name_kh')
                ->orderBy('name_eg')
                ->get());
            
            
        });
    }

    public function getShifts()
    {
        return $this->trace(__FUNCTION__, function () {
            return $this->rememberLookup(__FUNCTION__, [], fn () => Shift::query()
                ->select('id', 'name_en', 'name_kh', 'time_range')
                ->orderBy('name_en')
                ->get());
            
            
        });
    }

    public function getClasses(mixed $majorId = null, mixed $shiftId = null)
    {
        return $this->trace(__FUNCTION__, function () use ($majorId, $shiftId) {
            $majorId = $this->toNullableInt($majorId);
            $shiftId = $this->toNullableInt($shiftId);
            
            return $this->rememberLookup(__FUNCTION__, [
                'major_id' => $majorId,
                'shift_id' => $shiftId,
            ], fn () => Classes::query()
                ->when(!is_null($majorId), fn ($query) => $query->where('major_id', $majorId))
                ->when(!is_null($shiftId), fn ($query) => $query->where('shift_id', $shiftId))
                ->select('id', 'code', 'major_id', 'shift_id', 'academic_year', 'year_level', 'semester', 'section', 'is_active')
                ->orderBy('code')
                ->get());
            
            
        });
    }

    public function getStudentTypes(): array
    {
        return $this->trace(__FUNCTION__, function (): array {
            return [
                ['value' => 'PAY', 'label' => 'PAY'],
                ['value' => 'PENDING', 'label' => 'PENDING'],
                ['value' => 'PASS', 'label' => 'PASS'],
                ['value' => 'FAIL', 'label' => 'FAIL'],
            ];
            
            
        });
    }

    public function getFullLookup(
        mixed $facultyId = null,
        mixed $majorId = null,
        mixed $provinceId = null,
        mixed $districtId = null,
        mixed $shiftId = null
    ): array {
        return $this->trace(__FUNCTION__, function () use ($facultyId, $majorId, $provinceId, $districtId, $shiftId): array {
            $facultyId = $this->toNullableInt($facultyId);
            $majorId = $this->toNullableInt($majorId);
            $provinceId = $this->toNullableInt($provinceId);
            $districtId = $this->toNullableInt($districtId);
            $shiftId = $this->toNullableInt($shiftId);
            
            return $this->rememberLookup(__FUNCTION__, [
                'faculty_id' => $facultyId,
                'major_id' => $majorId,
                'province_id' => $provinceId,
                'district_id' => $districtId,
                'shift_id' => $shiftId,
            ], fn (): array => [
                'faculties' => $this->getFaculties(),
                'majors' => $this->getMajorsByFaculty($facultyId),
                'subjects' => $this->getSubjectsByMajor($majorId),
                'provinces' => $this->getProvinces(),
                'districts' => $this->getDistrictsByProvince($provinceId),
                'communes' => $this->getCommunesByDistrict($districtId),
                'shifts' => $this->getShifts(),
                'classes' => $this->getClasses($majorId, $shiftId),
                'student_types' => $this->getStudentTypes(),
            ]);
            
            
        });
    }

    private function rememberLookup(string $name, array $params, callable $callback): mixed
    {
        if (!config('cache.lookup.enabled', true)) {
            return $callback();
        }

        $ttlSeconds = max(30, (int) config('cache.lookup.ttl_seconds', 300));
        $key = $this->lookupCacheKey($name, $params);

        return Cache::remember($key, now()->addSeconds($ttlSeconds), $callback);
    }

    private function lookupCacheKey(string $name, array $params): string
    {
        ksort($params);
        $payload = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'lookup:' . $name . ':' . sha1((string) $payload);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}



