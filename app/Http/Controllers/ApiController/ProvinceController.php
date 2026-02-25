<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvinceRequest;
use App\Http\Resources\ProvinceResource;
use App\Traits\ApiResponseTrait;

class ProvinceController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected \App\Services\ProvinceService $service
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(ProvinceRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Create province successfully!");
    }

    public function show($id)
    {
        $province = $this->service->findById($id);
        return $this->success(new ProvinceResource($province));
    }

    public function update(ProvinceRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("Province updated successfully!");
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Province deleted successfully!");
    }
}
