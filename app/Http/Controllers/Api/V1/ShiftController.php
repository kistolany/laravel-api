<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ShiftRequest;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\Shift;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;


class ShiftController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected \App\Services\ShiftService $service
    ){}

    public function index()
    {
        $shifts = Shift::all();
        return $this->success($this->service->index());
    }

    public function store(ShiftRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Create shift success fully !");
    }

    public function show($id)
    {
        $shift = $this->service->findById($id);
        return $this->success(new ShiftResource($shift));
    }

    public function update(ShiftRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("Shift updated successfully!");
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Shift deleted successfully!");
    }
    

}
