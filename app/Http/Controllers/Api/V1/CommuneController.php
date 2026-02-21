<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommuneRequest;
use App\Http\Resources\Api\V1\CommuneResource;
use App\Services\CommuneService;
use App\Traits\ApiResponseTrait;

class CommuneController extends Controller
{
    use ApiResponseTrait;

    /**
     * Inject the CommuneService
     */
    public function __construct(
        protected CommuneService $service
    ) {}

    /**
     * Display a listing of the communes.
     */
    public function index()
    {
        return $this->success($this->service->index());
    }

    /**
     * Store a newly created commune.
     */
    public function store(CommuneRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Commune created successfully!");
    }

    /**
     * Display the specified commune.
     */
    public function show($id)
    {
        $commune = $this->service->findById($id);
        return $this->success(new CommuneResource($commune));
    }

    /**
     * Update the specified commune.
     */
    public function update(CommuneRequest $request, $id)
    {
        $this->service->update($id, $request->all());
        return $this->success("Commune updated successfully!");
    }

    /**
     * Remove the specified commune.
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success("Commune deleted successfully!");
    }
}
