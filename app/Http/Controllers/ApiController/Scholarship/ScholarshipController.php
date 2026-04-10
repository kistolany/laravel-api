<?php

namespace App\Http\Controllers\ApiController\Scholarship;

use App\Http\Controllers\Controller;
use App\Http\Requests\Scholarship\ScholarshipRequest;
use App\Http\Resources\Scholarship\ScholarshipResource;
use App\Services\Scholarship\ScholarshipService;
use App\Traits\ApiResponseTrait;

class ScholarshipController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ScholarshipService $service
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(ScholarshipRequest $request)
    {
        $this->service->create($request->validated());
        return $this->success("Scholarship registered successfully!");
    }

    public function show($id)
    {
        $scholarship = $this->service->findById($id);
        return $this->success(new ScholarshipResource($scholarship));
    }

    public function update(ScholarshipRequest $request, $id)
    {
        $this->service->update($id, $request->validated());
        return $this->success("Scholarship updated successfully!");
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success(null, "Scholarship deleted successfully!");
    }
}

