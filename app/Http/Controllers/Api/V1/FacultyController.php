<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FacultyRequest;
use App\Http\Resources\Api\V1\FacultyResource;
use App\Services\FacultyService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class FacultyController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected \App\Services\FacultyService $service
    ) {}


    public function index(FacultyService $service)
    {
        // call FacultyService and return success trait
        return $this->success($service->index());
    }

    public function store(FacultyRequest $request)
    {
        // The Controller calls the Service function
        $this->service->create($request->all());
        return $this->success("Create faculity success fully !");
    }


    public function show($id)
    {
        // call service for validate method
       $faculty = $this->service->findById($id);

       // if success respone resource
        return $this->success(new FacultyResource($faculty));
    }


    public function update(FacultyRequest $request, $id)
    {
        //  directly to the service
        $this->service->update($id, $request->all());

        // return success trait 
        return $this->success("Faculty updated successfully!");
    }

    public function destroy($id)
    {
        // call delete service method 
        $this->service->delete($id);

        // return message when success
        return $this->success("Faculty deleted successfully!");
    }
}
