<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MajorRequest;
use App\Http\Resources\Api\V1\MajorResource;
use App\Traits\ApiResponseTrait;

class MajorController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected \App\Services\MajorService $service
    ) {}


    public function index()
    {
        // call FacultyService and return success trait
        return $this->success($this->service->index());
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

    public function getByFaculty($facultyId)
    {
        // call method for find all by faculty
        $majors = $this->service->findAllByFacultyId($facultyId);

        return response()->json([
            'status' => 'success',
            'data' => $majors
        ]);
    }
}
