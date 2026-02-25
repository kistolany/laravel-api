<?php

namespace App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\FacultyRequest;
use App\Http\Resources\FacultyResource;
use App\Traits\ApiResponseTrait;


class FacultyController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected \App\Services\FacultyService $service
    ) {}


    public function index()
    {
        // call FacultyService and return success trait
        return $this->success($this->service->index());
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
