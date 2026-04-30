<?php

namespace App\Http\Controllers\ApiController\Shift;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\ShiftRequest;
use App\Http\Resources\Shift\ShiftResource;
use App\Traits\ApiResponseTrait;


class ShiftController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected \App\Services\Shift\ShiftService $service
    ){}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(ShiftRequest $request)
    {
        $this->service->create($request->validated());
        return $this->success("Create shift success fully !");
    }

    
    public function show($id)
    {
        $shift = $this->service->findById($id);
        return $this->success(new ShiftResource($shift));
    }


    public function update(ShiftRequest $request, $id)
    {
        $this->service->update($id, $request->validated());
        return $this->success("Shift updated successfully!");
    }


    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Shift deleted successfully!");
    }
    

}

