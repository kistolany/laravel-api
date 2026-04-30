<?php

namespace App\Http\Controllers\ApiController\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StudentImageRequest;
use App\Http\Requests\Student\StudentRequest;
use App\Http\Requests\Student\StudentStatusRequest;
use App\Http\Requests\Student\StudentTypeRequest;
use App\Http\Resources\Student\StudentResource;
use App\Http\Resources\Student\StudentClassResource;
use App\Services\Student\StudentService;
use App\Traits\ApiResponseTrait;



class StudentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected StudentService $service
    ) {}

    /**
     * Display a listing of students with pagination.
     */
    public function index()
    {
        // The service already handles the pagination and resource collection
        return $this->success($this->service->index());
    }

    /**
     * Display only students with student_type PAY or PASS.
     */
    public function payOrPass()
    {
        return $this->success($this->service->payOrPass());
    }

    /**
     * Display PAY/PASS students eligible for the final exam list.
     */
    public function finalExamList()
    {
        return $this->success($this->service->finalExamList());
    }

    /**
     * Display only students with student_type PASS.
     */
    public function passStudents()
    {
        return $this->success($this->service->passStudents());
    }

    /**
     * Display only students with student_type FAIL.
     */
    public function failStudents()
    {
        return $this->success($this->service->failStudents());
    }

    /**
     * Display only students with student_type PENDING (scholarship waiting for exam result).
     */
    public function pendingStudents()
    {
        return $this->success($this->service->pendingStudents());
    }

    /**
     * Display archived students that can be restored.
     */
    public function archived()
    {
        return $this->success($this->service->archived());
    }

    /**
     * Store a newly created student (and their academic info).
     */
    public function store(StudentRequest $request) 
    {
        $student = $this->service->create($request->validated());
        
        return $this->success(new StudentResource($student), "Student created successfully!");
    }

    /**
     * Display the specified student with full details.
     */
    public function show($id)
    {
        $student = $this->service->findById($id);
        return $this->success(new StudentResource($student));
    }

    /**
     * Update the specified student and their academic info.
     */
    public function update(StudentRequest $request, $id)
    {
        $student = $this->service->update($id, $request->validated());
        
        return $this->success(
            new StudentResource($student), 
            "Student updated successfully!"
        );
    }

    /**
     * Remove the specified student.
     */
    public function destroy($id)
    {
        $this->service->delete($id);
        return $this->success(null, "Student archived successfully!");
    }

    /**
     * Restore an archived student.
     */
    public function restore($id)
    {
        $student = $this->service->restore($id);
        return $this->success(new StudentResource($student), "Student restored successfully!");
    }

    /**
     * Set student status to disable.
     */
    public function setDisable($id)
    {
        $student = $this->service->setStatus($id, 'inactive');
        return $this->success(new StudentResource($student), "Student status updated to inactive.");
    }

    /**
     * Update student status to enable or disable.
     */
    public function updateStatus(StudentStatusRequest $request, $id)
    {
        $status = $request->validated('status');

        $student = $this->service->setStatus($id, $status);
        return $this->success(new StudentResource($student), "Student status updated to {$status}.");
    }

    /**
     * Update student type only.
     */
    public function updateStudentType(StudentTypeRequest $request, $id)
    {
        $validated = $request->validated();
        $student = $this->service->setStudentType(
            $id,
            $validated['student_type'],
            $validated['tuition_plan'] ?? null
        );
        return $this->success(new StudentResource($student), "Student type updated successfully.");
    }

    /**
     * Update student image.
     */
    public function updateImage(StudentImageRequest $request, $id)
    {
        $student = $this->service->updateImage($id, $request->file('image'));
        return $this->success(new StudentResource($student), "Student image updated successfully!");
    }

    /**
     * Get classes for a student.
     */
    public function classes($id)
    {
        $student = $this->service->classes($id);
        return $this->success(StudentClassResource::collection($student->classes));
    }
}
