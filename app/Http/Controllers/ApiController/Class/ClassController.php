<?php

namespace App\Http\Controllers\ApiController\Class;

use App\Http\Controllers\Controller;
use App\Http\Requests\Class\ClassBulkStudentRequest;
use App\Http\Requests\Class\ClassRequest;
use App\Http\Requests\Class\ClassSubjectAssignRequest;
use App\Http\Requests\Class\ClassStudentRequest;
use App\Http\Resources\Class\ClassResource;
use App\Http\Resources\Class\ClassStudentResource;
use App\Services\Class\ClassService;
use App\Services\Major\Major_subject_service;
use App\Traits\ApiResponseTrait;

class ClassController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ClassService $service,
        protected Major_subject_service $majorSubjectService
    ) {}

    public function index()
    {
        return $this->success($this->service->index());
    }

    public function store(ClassRequest $request)
    {
        $this->service->create($request->all());
        return $this->success("Class created successfully!");
    }

    public function show($id)
    {
        $class = $this->service->findById($id, true);
        return $this->success(new ClassResource($class));
    }

    public function destroy($id)
    {
        $this->service->delete((int) $id);
        return $this->success(null, "Class deleted successfully!");
    }

    public function students($id)
    {
        $class = $this->service->findById($id, true);
        $class->students->load('academicInfo.major');
        $className = $class->code ?? $class->name;
        $collection = ClassStudentResource::collection($class->students);
        $collection->each(fn($r) => $r->additional(['class_name' => $className]));
        return $this->success($collection);
    }

    public function addStudent(ClassStudentRequest $request, $id)
    {
        $student = $this->service->addStudent($id, $request->all());
        return $this->success(new ClassStudentResource($student), "Student added to class successfully!");
    }

    public function removeStudent($id, $studentId)
    {
        $this->service->removeStudent((int) $id, $studentId);
        return $this->success(null, "Student removed from class successfully!");
    }

    public function addStudentsByMajor(ClassBulkStudentRequest $request, $id)
    {
        $result = $this->service->addStudentsByMajor($id, $request->all());
        return $this->success($result, "Students added to class successfully!");
    }

    public function subjects($id)
    {
        $data = $this->majorSubjectService->getByClass((int) $id);

        return $this->success($data, 'Class subjects retrieved successfully.');
    }

    public function assignSubject(ClassSubjectAssignRequest $request, $id)
    {
        $data = $this->majorSubjectService->createFromClass((int) $id, $request->validated());

        return $this->success($data, 'Subject assigned to class successfully!');
    }
}

