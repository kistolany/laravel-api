<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Services\LookupService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected LookupService $service
    ) {}

    /**
     * Get faculties for the first dropdown.
     */
    public function faculties()
    {
        return $this->success($this->service->getFaculties());
    }

    /**
     * Get majors, optionally filtered by faculty_id for cascading dropdowns.
     */
    public function majors(Request $request)
    {
        $facultyId = $request->filled('faculty_id')
            ? (int) $request->query('faculty_id')
            : null;

        $data = $this->service->getMajorsByFaculty($facultyId);

        return $this->success($data);
    }

    /**
     * Get provinces for the first dropdown.
     */
    public function provinces()
    {
        return $this->success($this->service->getProvinces());
    }

    /**
     * Get districts based on province_id.
     */
    public function districts(Request $request)
    {
        $provinceId = $request->query('province_id');

        if (!$provinceId) {
            return $this->success([]);
        }

        $data = $this->service->getDistrictsByProvince((int) $provinceId);

        return $this->success($data);
    }

    /**
     * Get communes based on district_id.
     */
    public function communes(Request $request)
    {
        $districtId = $request->query('district_id');

        if (!$districtId) {
            return $this->success([]);
        }

        $data = $this->service->getCommunesByDistrict((int) $districtId);

        return $this->success($data);
    }

    /**
     * Get subjects, optionally filtered by major_id for cascading dropdowns.
     */
    public function subjects(Request $request)
    {
        $majorId = $request->filled('major_id')
            ? (int) $request->query('major_id')
            : null;

        $data = $this->service->getSubjectsByMajor($majorId);

        return $this->success($data);
    }
}
