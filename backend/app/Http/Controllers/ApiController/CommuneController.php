<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommuneRequest;
use App\Http\Resources\CommuneResource;
use App\Traits\ApiResponseTrait;

class CommuneController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected \App\Services\CommuneService $service
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(CommuneRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Create commune successfully!");
    }

    public function show($id)
    {
        $commune = $this->service->findById($id);
        return $this->success(new CommuneResource($commune));
    }

    public function update(CommuneRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("Commune updated successfully!");
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Commune deleted successfully!");
    }
}
