<?php

namespace App\Http\Controllers\ApiController\AcademicInfo;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicInfo\AcademicInfoResource;
use App\Models\AcademicInfo;
use App\Traits\ApiResponseTrait;

class AcademicInfoController extends Controller
{
    use ApiResponseTrait;

    public function getByMajorId(int $majorId)
    {
        $data = AcademicInfo::with(['major', 'shift'])
            ->where('major_id', $majorId)
            ->get();

        return $this->success(AcademicInfoResource::collection($data));
    }

    public function getByShiftId(int $shiftId)
    {
        $data = AcademicInfo::with(['major', 'shift'])
            ->where('shift_id', $shiftId)
            ->get();

        return $this->success(AcademicInfoResource::collection($data));
    }
}

