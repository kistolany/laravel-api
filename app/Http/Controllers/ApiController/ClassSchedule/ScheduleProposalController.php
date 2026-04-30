<?php

namespace App\Http\Controllers\ApiController\ClassSchedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassSchedule\MyScheduleProposalRequest;
use App\Http\Requests\ClassSchedule\ScheduleProposalRequest;
use App\Http\Requests\ClassSchedule\ScheduleProposalResendRequest;
use App\Http\Requests\ClassSchedule\ScheduleProposalRespondRequest;
use App\Http\Resources\ClassSchedule\ScheduleProposalResource;
use App\Services\ClassSchedule\ScheduleProposalService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class ScheduleProposalController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected ScheduleProposalService $service
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            ScheduleProposalResource::collection($this->service->index()),
            'Proposals retrieved.'
        );
    }

    public function store(ScheduleProposalRequest $request): JsonResponse
    {
        return $this->success(
            new ScheduleProposalResource($this->service->create($request->validated(), $request->user())),
            'Proposal sent to teacher.'
        );
    }

    public function resend(ScheduleProposalResendRequest $request, int $id): JsonResponse
    {
        return $this->success(
            new ScheduleProposalResource($this->service->resend($id, $request->validated(), $request->user())),
            'Proposal resent to new teacher.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->delete($id);

        return $this->success(null, 'Proposal deleted.');
    }

    public function mine(MyScheduleProposalRequest $request): JsonResponse
    {
        return $this->success(
            ScheduleProposalResource::collection(
                $this->service->mine($request->user(), $request->validated('teacher_id'))
            ),
            'My proposals retrieved.'
        );
    }

    public function respond(ScheduleProposalRespondRequest $request, int $id): JsonResponse
    {
        $proposal = $this->service->respond($id, $request->validated());

        return $this->success(
            new ScheduleProposalResource($proposal),
            $this->service->responseMessage($proposal)
        );
    }
}
