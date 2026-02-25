<?php

namespace App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\MajorSubjectRequest;
use App\Services\Major_subject_service; // Correct Service
use App\Traits\ApiResponseTrait;

class MajorSubjectController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected Major_subject_service $service
    ) {}

    /**
     * Get the list of assigned subjects (The Curriculum)
     */
    public function index()
    {
        return $this->success($this->service->index());
    }

    /**
     * Create a new assignment (Assign subject to Major/Year/Sem)
     */
    public function store(MajorSubjectRequest $request)
    {
        // Validated by MajorSubjectRequest
        $data = $this->service->create($request->validated());

        // Return the Resource so the frontend sees the names immediately
        return $this->success("Subject assigned to curriculum successfully!" );
    }

    /**
     * Update an assignment (Change year or semester for a subject)
     */
    public function update(MajorSubjectRequest $request, $id)
    {
        $data = $this->service->update($id, $request->validated());

        return $this->success("major & subject updated successfully!" );
    }

    /**
     * Remove the subject from the Major's curriculum
     */
    public function destroy($id)
    {
        $this->service->delete($id);

        return $this->success("Subject removed from major successfully!");
    }
}
