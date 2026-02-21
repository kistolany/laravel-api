<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Province;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProvinceRequest; // Request for validation
use App\Http\Resources\Api\V1\ProvinceResource; // Resource for formatting response
use App\Services\ProvinceService; // Service for business logic
use App\Traits\ApiResponseTrait;

class ProvinceController extends Controller
{
    use ApiResponseTrait;

    // Inject ProvinceService to use in all methods
    public function __construct(
        protected ProvinceService $service
    ) {}

    /**
     * List all provinces (with optional search)
     */
    public function index()
    {
        $provinces = $this->service->index();
        return $this->success($provinces);
    }

    public function store(ProvinceRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Create provinces successfully!");
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
