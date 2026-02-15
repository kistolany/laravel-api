<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcademicInfoRequest;
use App\Http\Resources\Api\V1\AcademicInfoResource;
use App\Models\AcademicInfo;
use App\Traits\ApiResponseTrait;
use App\Services\AcademicInfoService;
use Illuminate\Http\Request;

class AcademicInfoController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected \App\Services\AcademicInfoService $service
    ){}
    public function index()
    {
        $shifts = AcademicInfo::all();
        return $this->success($this->service->index());
    }
    public function store(AcademicInfoRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Create academic info success fully !");
    }
    public function show($id)
    {
        $shift = $this->service->findById($id);
        return $this->success(new AcademicInfoResource($shift));
    }
    public function update(AcademicInfoRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("Academic info updated successfully!");
    }
    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Academic info deleted successfully!");
    }
}
