<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\District;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\Province;

class LookupService extends BaseService
{
    /**
     * Get all faculties for the top-level dropdown.
     */
    public function getFaculties()
    {
        // Make sure the method name matches: getFaculties
        return Faculty::select('id', 'name_eg', 'name_kh')
            ->orderBy('name_eg')
            ->get();
    }

    /**
     * Get majors for a specific faculty to populate the second dropdown.
     */
    public function getMajorsByFaculty(int $facultyId)
    {
        return Major::where('faculty_id', $facultyId)
            ->select('id', 'name_eg', 'name_kh')
            ->orderBy('name_eg')
            ->get();
    }

    public function getProvinces()
    {
        return Province::select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function getDistrictsByProvince(int $provinceId)
    {
        return District::where('province_id', $provinceId)
            ->select('id', 'name', 'province_id')
            ->orderBy('name')
            ->get();
    }

    public function getCommunesByDistrict(int $districtId)
    {
        return Commune::where('district_id', $districtId)
            ->select('id', 'name', 'district_id')
            ->orderBy('name')
            ->get();
    }
}
