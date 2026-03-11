<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\DistrictRequest;
use App\Http\Resources\DistrictResource;
use App\Traits\ApiResponseTrait;

class DistrictController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected \App\Services\DistrictService $service
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(DistrictRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Create district successfully!");
    }

    public function show($id)
    {
        $district = $this->service->findById($id);
        return $this->success(new DistrictResource($district));
    }

    public function update(DistrictRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("District updated successfully!");
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("District deleted successfully!");
    }
}
