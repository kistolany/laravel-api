<?php

namespace App\Http\Controllers\ApiController;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassBulkStudentRequest;
use App\Http\Requests\ClassRequest;
use App\Http\Requests\ClassStudentRequest;
use App\Http\Resources\ClassResource;
use App\Http\Resources\ClassStudentResource;
use App\Services\ClassService;
use App\Traits\ApiResponseTrait;

class ClassController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ClassService $service
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

    public function students($id)
    {
        $class = $this->service->findById($id, true);
        return $this->success(ClassStudentResource::collection($class->students));
    }

    public function addStudent(ClassStudentRequest $request, $id)
    {
        $student = $this->service->addStudent($id, $request->all());
        return $this->success(new ClassStudentResource($student), "Student added to class successfully!");
    }

    public function addStudentsByMajor(ClassBulkStudentRequest $request, $id)
    {
        $result = $this->service->addStudentsByMajor($id, $request->all());
        return $this->success($result, "Students added to class successfully!");
    }
}
