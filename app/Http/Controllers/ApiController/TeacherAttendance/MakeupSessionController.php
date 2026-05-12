<?php

namespace App\Http\Controllers\ApiController\TeacherAttendance;

use App\Http\Controllers\Controller;
use App\Services\TeacherAttendance\MakeupSessionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MakeupSessionController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private MakeupSessionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'schedule_id' => ['nullable', 'integer', 'exists:class_schedules,id'],
            'teacher_id'  => ['nullable', 'integer', 'exists:teachers,id'],
            'status'      => ['nullable', 'string', Rule::in(['scheduled', 'completed', 'cancelled'])],
        ]);

        return $this->success($this->service->index($filters));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_id'        => ['required', 'integer', 'exists:class_schedules,id'],
            'makeup_schedule_id' => ['nullable', 'integer', 'exists:class_schedules,id'],
            'teacher_id'         => ['nullable', 'integer', 'exists:teachers,id'],
            'makeup_date'        => ['required', 'date'],
            'makeup_session'     => ['required', 'integer', Rule::in([1, 2])],
            'shift_id'           => ['nullable', 'integer', 'exists:shifts,id'],
            'absent_week_number' => ['required', 'integer', 'min:1', 'max:15'],
            'absent_date'        => ['required', 'date'],
            'note'               => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->success($this->service->store($data, Auth::id()));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'makeup_date'        => ['sometimes', 'date'],
            'makeup_session'     => ['sometimes', 'integer', Rule::in([1, 2])],
            'makeup_schedule_id' => ['nullable', 'integer', 'exists:class_schedules,id'],
            'shift_id'           => ['nullable', 'integer', 'exists:shifts,id'],
            'absent_week_number' => ['sometimes', 'integer', 'min:1', 'max:15'],
            'absent_date'        => ['sometimes', 'date'],
            'attendance_status'  => ['nullable', 'string', Rule::in(['Present', 'Absent'])],
            'note'               => ['nullable', 'string', 'max:1000'],
            'teacher_id'         => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        return $this->success($this->service->update($id, $data, Auth::id()));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->destroy($id);
        return $this->success(['message' => 'Makeup session deleted.']);
    }
}
