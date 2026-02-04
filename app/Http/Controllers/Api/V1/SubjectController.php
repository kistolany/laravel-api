<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SubjectRequest;
use App\Http\Resources\Api\V1\SubjectResource;
use App\Services\SubjectService;
use App\Traits\ApiResponseTrait;

class SubjectController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected \App\Services\SubjectService $service
    ) {}


    public function index()
    {
        // call FacultyService and return success trait
        return $this->success($this->service->index());
    }

    public function store(SubjectRequest $request)
    {
        // The Controller calls the Service function
        $this->service->create($request->all());
        return $this->success("Create subject success fully !");
    }


    public function show($id)
    {
        // call service for validate method
       $faculty = $this->service->findById($id);

       // if success respone resource
        return $this->success(new SubjectResource($faculty));
    }


    public function update(SubjectRequest $request, $id)
    {
        //  directly to the service
        $this->service->update($id, $request->all());

        // return success trait 
        return $this->success("Subject updated successfully!");
    }

    public function destroy($id)
    {
        // call delete service method 
        $this->service->delete($id);

        // return message when success
        return $this->success("Subject deleted successfully!");
    }
}
