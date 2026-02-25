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
     * Get majors based on faculty_id for the second dropdown.
     */
    public function majors(Request $request)
    {
        $facultyId = $request->query('faculty_id');

        if (!$facultyId) {
            return $this->success([]); // Return empty data if no faculty selected
        }

        $data = $this->service->getMajorsByFaculty((int) $facultyId);
        
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
}
