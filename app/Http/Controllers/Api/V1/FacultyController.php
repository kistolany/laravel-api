<?php

namespace App\Http\Controllers\Api\V1;


use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FacultyRequest;
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
       return $this->success($service->index());
    }

    public function store(FacultyRequest $request)
    {
        // The Controller calls the Service function
       $this->service->create($request->all());
        return $this->success( "Create faculity success fully !");
    }


    public function show(string $id)
    {
        //
    }


    public function update(Request $request, string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        //
    }
}
