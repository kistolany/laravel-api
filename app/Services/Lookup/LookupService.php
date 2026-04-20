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
use App\Models\Teacher;
use Illuminate\Support\Facades\Cache;
class LookupService extends BaseService
{
    /**
     * Get all faculties for the top-level dropdown.
     */
    public function getFaculties()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Faculty::select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    /**
     * Get majors for a specific faculty, or all majors when no faculty is selected.
     */
    public function getMajorsByFaculty(mixed $facultyId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $facultyId) {
            $facultyId = $this->toNullableInt($facultyId);
            return $this->rememberLookup($fn, ['faculty_id' => $facultyId], fn () => Major::query()
                ->when(!is_null($facultyId), fn ($query) => $query->where('faculty_id', $facultyId))
                ->select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getProvinces()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Province::select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getDistrictsByProvince(mixed $provinceId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $provinceId) {
            $provinceId = $this->toNullableInt($provinceId);
            if ($provinceId === null) {
                return collect();
            }
            return $this->rememberLookup($fn, ['province_id' => $provinceId], fn () => District::where('province_id', $provinceId)
                ->select('id', 'name', 'province_id')
                ->orderBy('name')
                ->get());
        });
    }

    public function getCommunesByDistrict(mixed $districtId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $districtId) {
            $districtId = $this->toNullableInt($districtId);
            if ($districtId === null) {
                return collect();
            }
            return $this->rememberLookup($fn, ['district_id' => $districtId], fn () => Commune::where('district_id', $districtId)
                ->select('id', 'name', 'district_id')
                ->orderBy('name')
                ->get());
        });
    }

    public function getSubjectsByMajor(mixed $majorId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $majorId) {
            $majorId = $this->toNullableInt($majorId);
            return $this->rememberLookup($fn, ['major_id' => $majorId], fn () => Subject::query()
                ->when(!is_null($majorId), function ($query) use ($majorId) {
                    $query->whereIn(
                        'id',
                        MajorSubject::query()
                            ->where('major_id', $majorId)
                            ->select('subject_id')
                    );
                })
                ->select('id', 'subject_Code', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getShifts()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Shift::query()
                ->select('id', 'name', 'time_range')
                ->orderBy('name')
                ->get());
        });
    }

    public function getClasses(mixed $majorId = null, mixed $shiftId = null)
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $majorId, $shiftId) {
            $majorId = $this->toNullableInt($majorId);
            $shiftId = $this->toNullableInt($shiftId);
            return $this->rememberLookup($fn, [
                'major_id' => $majorId,
                'shift_id' => $shiftId,
            ], fn () => Classes::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get());
        });
    }

    public function getStages(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn () =>
                \App\Models\AcademicInfo::select('stage')
                    ->distinct()
                    ->whereNotNull('stage')
                    ->orderBy('stage')
                    ->pluck('stage')
                    ->map(fn ($v) => ['value' => $v, 'label' => $v])
                    ->toArray()
            );
        });
    }

    public function getBatchYears(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn () =>
                \App\Models\AcademicInfo::select('batch_year')
                    ->distinct()
                    ->whereNotNull('batch_year')
                    ->orderByDesc('batch_year')
                    ->pluck('batch_year')
                    ->map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v])
                    ->toArray()
            );
        });
    }

    public function getStudyDays(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return $this->rememberLookup($fn, [], fn () =>
                \App\Models\AcademicInfo::select('study_days')
                    ->distinct()
                    ->whereNotNull('study_days')
                    ->orderBy('study_days')
                    ->pluck('study_days')
                    ->map(fn ($v) => ['value' => $v, 'label' => $v])
                    ->toArray()
            );
        });
    }

    public function getStudentTypes(): array
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn): array {
            return [
                ['value' => 'PAY',     'label' => 'PAY'],
                ['value' => 'PENDING', 'label' => 'PENDING'],
                ['value' => 'PASS',    'label' => 'PASS'],
                ['value' => 'FAIL',    'label' => 'FAIL'],
            ];
        });
    }

    public function getTeachers()
    {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn) {
            return $this->rememberLookup($fn, [], fn () => Teacher::query()
                ->select('id', 'first_name', 'last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn ($t) => [
                    'id'   => $t->id,
                    'name' => trim($t->first_name . ' ' . $t->last_name),
                ]));
        });
    }

    public function getFullLookup(
        mixed $facultyId = null,
        mixed $majorId = null,
        mixed $provinceId = null,
        mixed $districtId = null,
        mixed $shiftId = null
    ): array {
        $fn = __FUNCTION__;
        return $this->trace($fn, function () use ($fn, $facultyId, $majorId, $provinceId, $districtId, $shiftId): array {
            $facultyId  = $this->toNullableInt($facultyId);
            $majorId    = $this->toNullableInt($majorId);
            $provinceId = $this->toNullableInt($provinceId);
            $districtId = $this->toNullableInt($districtId);
            $shiftId    = $this->toNullableInt($shiftId);
            return $this->rememberLookup($fn, [
                'faculty_id'  => $facultyId,
                'major_id'    => $majorId,
                'province_id' => $provinceId,
                'district_id' => $districtId,
                'shift_id'    => $shiftId,
            ], fn (): array => [
                'faculties'     => $this->getFaculties(),
                'majors'        => $this->getMajorsByFaculty($facultyId),
                'subjects'      => $this->getSubjectsByMajor($majorId),
                'provinces'     => $this->getProvinces(),
                'districts'     => $this->getDistrictsByProvince($provinceId),
                'communes'      => $this->getCommunesByDistrict($districtId),
                'shifts'        => $this->getShifts(),
                'classes'       => $this->getClasses($majorId, $shiftId),
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



