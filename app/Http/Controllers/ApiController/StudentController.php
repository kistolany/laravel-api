<?php

namespace App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentImageRequest;
use App\Http\Requests\StudentRequest;
use App\Http\Resources\StudentResource;
use App\Http\Resources\StudentClassResource;
use App\Services\StudentService;
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
     * Store a newly created student (and their academic info).
     */
    public function store(StudentRequest $request) 
    {
        // Using Request or a custom StudentRequest for validation
        $student = $this->service->create($request->all());
        
        return $this->success( "Student created successfully!");
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
        $student = $this->service->update($id, $request->all());
        
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
        return $this->success(null, "Student deleted successfully!");
    }

    /**
     * Set student status to inactive.
     */
    public function setInactive($id)
    {
        $student = $this->service->setStatus($id, 'inactive');
        return $this->success(new StudentResource($student), "Student status updated to inactive.");
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
