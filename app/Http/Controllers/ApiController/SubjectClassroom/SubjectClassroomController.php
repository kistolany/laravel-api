<?php

namespace App\Http\Controllers\ApiController\SubjectClassroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectClassroom\GradeHomeworkSubmissionRequest;
use App\Http\Requests\SubjectClassroom\HomeworkAssignmentRequest;
use App\Http\Requests\SubjectClassroom\HomeworkSubmissionRequest;
use App\Http\Requests\SubjectClassroom\ReviewHomeworkSubmissionRequest;
use App\Http\Requests\SubjectClassroom\SubjectClassroomListRequest;
use App\Http\Requests\SubjectClassroom\SubjectClassroomOptionsRequest;
use App\Http\Requests\SubjectClassroom\SubjectLessonRequest;
use App\Services\SubjectClassroom\SubjectClassroomService;

class SubjectClassroomController extends Controller
{
    public function __construct(
        private SubjectClassroomService $service
    ) {}

    public function options(SubjectClassroomOptionsRequest $request)
    {
        return $this->service->options($request);
    }

    public function lessons(SubjectClassroomListRequest $request)
    {
        return $this->service->lessons($request);
    }

    public function storeLesson(SubjectLessonRequest $request)
    {
        return $this->service->storeLesson($request);
    }

    public function destroyLesson($id)
    {
        return $this->service->destroyLesson($id);
    }

    public function homework(SubjectClassroomListRequest $request)
    {
        return $this->service->homework($request);
    }

    public function storeHomework(HomeworkAssignmentRequest $request)
    {
        return $this->service->storeHomework($request);
    }

    public function updateHomework(HomeworkAssignmentRequest $request, $id)
    {
        return $this->service->updateHomework($request, $id);
    }

    public function destroyHomework($id)
    {
        return $this->service->destroyHomework($id);
    }

    public function submissions($id)
    {
        return $this->service->submissions($id);
    }

    public function submitHomework(HomeworkSubmissionRequest $request, $id)
    {
        return $this->service->submitHomework($request, $id);
    }

    public function gradeSubmission(GradeHomeworkSubmissionRequest $request, $id)
    {
        return $this->service->gradeSubmission($request, $id);
    }

    public function reviewSubmission(ReviewHomeworkSubmissionRequest $request, $id)
    {
        return $this->service->reviewSubmission($request, $id);
    }
}
