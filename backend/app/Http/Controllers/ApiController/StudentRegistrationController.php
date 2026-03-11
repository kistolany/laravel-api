<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRegistrationRequest;
use App\Http\Resources\StudentRegistrationResource;
use App\Services\StudentRegistrationService;
use App\Traits\ApiResponseTrait;

class StudentRegistrationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected StudentRegistrationService $service
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(StudentRegistrationRequest $request)
    {
        $this->service->create($request->validated());
        return $this->success("Student registration created successfully!");
    }

    public function show($id)
    {
        $registration = $this->service->findById($id);
        return $this->success(new StudentRegistrationResource($registration));
    }

    public function update(StudentRegistrationRequest $request, $id)
    {
        $this->service->update($id, $request->validated());
        return $this->success("Student registration updated successfully!");
    }

    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success(null, "Student registration deleted successfully!");
    }
}
