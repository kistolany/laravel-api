<?php

namespace App\Http\Controllers\ApiController\Major;
use App\Http\Controllers\Controller;
use App\Http\Requests\Major\MajorRequest;
use App\Http\Resources\Major\MajorResource;
use App\Traits\ApiResponseTrait;

class MajorController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected \App\Services\Major\MajorService $service
    ) {}


    public function index()
    {
        // call FacultyService and return success trait
        return $this->success($this->service->index());
    }

    public function getByFaculty(int $facultyId)
    {
        $data = $this->service->getByFaculty($facultyId);

        return $this->success(MajorResource::collection($data));
    }

    public function store(MajorRequest $request)
    {
        // The Controller calls the Service function
        $this->service->create($request->all());
        return $this->success("Create major success fully !");
    }


    public function show($id)
    {
        // call service for validate method
        $faculty = $this->service->findById($id);

        // if success respone resource
        return $this->success(new MajorResource($faculty));
    }


    public function update(MajorRequest $request, $id)
    {
        //  directly to the service
        $this->service->update($id, $request->all());

        // return success trait 
        return $this->success("Major updated successfully!");
    }

    public function destroy($id)
    {
        // call delete service method 
        $this->service->delete($id);

        // return message when success
        return $this->success("Major deleted successfully!");
    }

}

