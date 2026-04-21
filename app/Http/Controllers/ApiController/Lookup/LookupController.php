<?php

namespace App\Http\Controllers\ApiController\Lookup;

use App\Http\Controllers\Controller;
use App\Services\Lookup\LookupService;
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
        $data = $this->service->getMajorsByFaculty($request->query('faculty_id'));

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
        $data = $this->service->getDistrictsByProvince($request->query('province_id'));

        return $this->success($data);
    }

    /**
     * Get communes based on district_id.
     */
    public function communes(Request $request)
    {
        $data = $this->service->getCommunesByDistrict($request->query('district_id'));

        return $this->success($data);
    }

    /**
     * Get subjects, optionally filtered by major_id for cascading dropdowns.
     */
    public function subjects(Request $request)
    {
        $data = $this->service->getSubjectsByMajor(
            $request->query('major_id'),
            $request->query('year_level'),
            $request->query('semester'),
        );

        return $this->success($data);
    }

    /**
     * Get shifts for dropdowns.
     */
    public function shifts()
    {
        return $this->success($this->service->getShifts());
    }

    /**
     * Get classes, optionally filtered by major_id and shift_id.
     */
    public function classes(Request $request)
    {
        $data = $this->service->getClasses(
            $request->query('major_id'),
            $request->query('shift_id'),
            $request->query('year_level'),
            $request->query('semester'),
        );

        return $this->success($data);
    }

    /**
     * Get student types for dropdowns.
     */
    public function studentTypes()
    {
        return $this->success($this->service->getStudentTypes());
    }

    public function stages()
    {
        return $this->success($this->service->getStages());
    }

    public function batchYears()
    {
        return $this->success($this->service->getBatchYears());
    }

    public function academicYears()
    {
        return $this->success($this->service->getAcademicYears());
    }

    public function semesters()
    {
        return $this->success($this->service->getSemesters());
    }

    public function studyDays()
    {
        return $this->success($this->service->getStudyDays());
    }

    public function scoreFilters()
    {
        return $this->success($this->service->getScoreFilters());
    }

    public function teachers()
    {
        return $this->success($this->service->getTeachers());
    }

    /**
     * Get full lookup payload for forms.
     */
    public function full(Request $request)
    {
        $data = $this->service->getFullLookup(
            $request->query('faculty_id'),
            $request->query('major_id'),
            $request->query('province_id'),
            $request->query('district_id'),
            $request->query('shift_id')
        );

        return $this->success($data);
    }
}
