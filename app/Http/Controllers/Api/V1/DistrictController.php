<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DistrictRequest;
use App\Http\Resources\Api\V1\DistrictResource;
use App\Services\DistrictService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class DistrictController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected \App\Services\DistrictService $service
    ) {}


    public function index(DistrictService $service)
    {
        // call DistrictService and return success trait
        return $this->success($service->index());
    }

    public function store(DistrictRequest $request)
    {
        // The Controller calls the Service function
        $this->service->create($request->all());
        return $this->success("Create district success fully !");
    }


    public function show($id)
    {
        // call service for validate method
       $district = $this->service->findById($id);

       // if success respone resource
        return $this->success(new DistrictResource($district));
    }


    public function update(DistrictRequest $request, $id)
    {
        //  directly to the service
        $this->service->update($id, $request->all());

        // return success trait 
        return $this->success("District updated successfully!");
    }

    public function destroy($id)
    {
        // call delete service method 
        $this->service->delete($id);

        // return message when success
        return $this->success("District deleted successfully!");
    }
}
