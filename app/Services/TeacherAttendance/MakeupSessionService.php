<?php

namespace App\Services\TeacherAttendance;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\ClassSchedule;
use App\Models\ScheduleMakeupSession;
use App\Models\TeacherAttendance;
use App\Services\BaseService;

class MakeupSessionService extends BaseService
{
    public function index(array $filters): array
    {
        return $this->trace(__FUNCTION__, function () use ($filters): array {
            $query = ScheduleMakeupSession::query()
                ->with([
                    'schedule:id,subject_id,shift_id,class_id,class_program_id,year_level',
                    'schedule.subject:id,name',
                    'schedule.shift:id,name',
                    'makeupSchedule:id,subject_id,shift_id,teacher_id',
                    'makeupSchedule.subject:id,name',
                    'makeupSchedule.shift:id,name',
                    'makeupShift:id,name',
                    'teacher:id,teacher_id,first_name,last_name',
                    'recorder:id,username,full_name',
                ])
                ->orderBy('makeup_date')
                ->orderBy('makeup_session');

            if (!empty($filters['schedule_id'])) {
                $query->where('schedule_id', $filters['schedule_id']);
            }
            if (!empty($filters['teacher_id'])) {
                $query->where('teacher_id', $filters['teacher_id']);
            }
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            return $query->get()->map(fn ($m) => $this->formatMakeup($m))->values()->all();
        });
    }

    public function store(array $data, int $recordedBy): array
    {
        return $this->trace(__FUNCTION__, function () use ($data, $recordedBy): array {
            $schedule = ClassSchedule::findOrFail($data['schedule_id']);

            $this->validateNoDuplicate(
                $data['schedule_id'],
                $data['absent_week_number'],
                $data['makeup_session'],
                null
            );

            // When a specific slot is chosen, the teacher is confirmed to teach —
            // mark attendance as Present immediately so no second step is needed.
            $makeupScheduleId = $data['makeup_schedule_id'] ?? null;
            $autoAttendance   = $makeupScheduleId ? 'Present' : null;
            $autoStatus       = $makeupScheduleId ? 'completed' : 'scheduled';

            $makeup = ScheduleMakeupSession::create([
                'schedule_id'        => $data['schedule_id'],
                'makeup_schedule_id' => $makeupScheduleId,
                'teacher_id'         => $data['teacher_id'] ?? $schedule->teacher_id,
                'makeup_date'        => $data['makeup_date'],
                'makeup_session'     => $data['makeup_session'],
                'shift_id'           => $data['shift_id'] ?? null,
                'attendance_status'  => $autoAttendance,
                'absent_week_number' => $data['absent_week_number'],
                'absent_date'        => $data['absent_date'],
                'status'             => $autoStatus,
                'note'               => $data['note'] ?? null,
                'recorded_by'        => $recordedBy,
            ]);

            if ($autoAttendance) {
                $this->syncMakeupAttendance($makeup, $autoAttendance, $recordedBy);
            }

            $makeup->load([
                'schedule:id,subject_id,shift_id,class_id,class_program_id,year_level',
                'schedule.subject:id,name',
                'schedule.shift:id,name',
                'makeupSchedule:id,subject_id,shift_id,teacher_id',
                'makeupSchedule.subject:id,name',
                'makeupSchedule.shift:id,name',
                'makeupShift:id,name',
                'teacher:id,teacher_id,first_name,last_name',
                'recorder:id,username,full_name',
            ]);

            return $this->formatMakeup($makeup);
        });
    }

    public function update(int $id, array $data, ?int $recordedBy = null): array
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data, $recordedBy): array {
            $makeup = ScheduleMakeupSession::findOrFail($id);

            $scheduleId       = $data['schedule_id'] ?? $makeup->schedule_id;
            $absentWeekNumber = $data['absent_week_number'] ?? $makeup->absent_week_number;
            $makeupSession    = $data['makeup_session'] ?? $makeup->makeup_session;

            if (
                $scheduleId !== $makeup->schedule_id ||
                $absentWeekNumber !== $makeup->absent_week_number ||
                $makeupSession !== $makeup->makeup_session
            ) {
                $this->validateNoDuplicate($scheduleId, $absentWeekNumber, $makeupSession, $id);
            }

            // Derive status from attendance_status when it is provided
            $attendanceStatusChanged = array_key_exists('attendance_status', $data);
            $attendanceStatus = $attendanceStatusChanged ? $data['attendance_status'] : $makeup->attendance_status;
            $status = $this->deriveStatus($attendanceStatus, $makeup->status);

            $makeupDate    = $data['makeup_date']    ?? $makeup->makeup_date;
            $makeupSession = $data['makeup_session'] ?? $makeup->makeup_session;

            $fields = [
                'makeup_date'        => $makeupDate,
                'makeup_session'     => $makeupSession,
                'makeup_schedule_id' => array_key_exists('makeup_schedule_id', $data) ? $data['makeup_schedule_id'] : $makeup->makeup_schedule_id,
                'absent_week_number' => $data['absent_week_number'] ?? $makeup->absent_week_number,
                'absent_date'        => $data['absent_date']        ?? $makeup->absent_date,
                'shift_id'           => array_key_exists('shift_id', $data) ? $data['shift_id'] : $makeup->shift_id,
                'attendance_status'  => $attendanceStatus,
                'status'             => $status,
                'note'               => array_key_exists('note', $data) ? $data['note'] : $makeup->note,
            ];

            $makeup->update($fields);

            // Sync a teacher_attendances record for the makeup date so the weekly
            // grid reflects the makeup attendance automatically.
            if ($attendanceStatusChanged) {
                $this->syncMakeupAttendance($makeup, $attendanceStatus, $recordedBy);
            }

            $makeup->load([
                'schedule:id,subject_id,shift_id,class_id,class_program_id,year_level',
                'schedule.subject:id,name',
                'schedule.shift:id,name',
                'makeupSchedule:id,subject_id,shift_id,teacher_id',
                'makeupSchedule.subject:id,name',
                'makeupSchedule.shift:id,name',
                'makeupShift:id,name',
                'teacher:id,teacher_id,first_name,last_name',
                'recorder:id,username,full_name',
            ]);

            return $this->formatMakeup($makeup);
        });
    }

    public function destroy(int $id): void
    {
        $this->trace(__FUNCTION__, function () use ($id): void {
            $makeup = ScheduleMakeupSession::findOrFail($id);

            // Remove the synced attendance record if attendance was already marked
            if ($makeup->attendance_status !== null) {
                $this->syncMakeupAttendance($makeup, null, null);
            }

            $makeup->delete();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Write or remove a teacher_attendances row for the makeup date/session.
     * This makes the makeup attendance visible in the weekly grid automatically.
     * Uses the original schedule_id so it counts against the correct subject slot.
     */
    private function syncMakeupAttendance(ScheduleMakeupSession $makeup, ?string $attendanceStatus, ?int $recordedBy): void
    {
        $makeupDate    = $makeup->makeup_date instanceof \Carbon\Carbon
            ? $makeup->makeup_date->format('Y-m-d')
            : (string) $makeup->makeup_date;
        $makeupSession = (int) $makeup->makeup_session;
        $teacherId     = $makeup->teacher_id;

        // Use the slot the teacher actually teaches in for the makeup.
        // makeup_schedule_id is the chosen slot; fall back to original schedule_id.
        $scheduleId = $makeup->makeup_schedule_id ?? $makeup->schedule_id;

        if ($attendanceStatus === null) {
            TeacherAttendance::where('schedule_id', $scheduleId)
                ->where('attendance_date', $makeupDate)
                ->where('session', $makeupSession)
                ->delete();
            return;
        }

        TeacherAttendance::updateOrCreate(
            [
                'schedule_id'     => $scheduleId,
                'attendance_date' => $makeupDate,
                'session'         => $makeupSession,
            ],
            [
                'teacher_id'  => $teacherId,
                'status'      => $attendanceStatus,
                'note'        => 'Makeup session',
                'recorded_by' => $recordedBy,
            ]
        );
    }

    /**
     * Present → completed, Absent → missed, null → keep current or 'scheduled'.
     */
    private function deriveStatus(?string $attendanceStatus, string $currentStatus): string
    {
        if ($attendanceStatus === 'Present') return 'completed';
        if ($attendanceStatus === 'Absent')  return 'missed';
        return $currentStatus;
    }

    private function validateNoDuplicate(int $scheduleId, int $absentWeekNumber, int $makeupSession, ?int $excludeId): void
    {
        $exists = ScheduleMakeupSession::query()
            ->where('schedule_id', $scheduleId)
            ->where('absent_week_number', $absentWeekNumber)
            ->where('makeup_session', $makeupSession)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($exists) {
            throw new ApiException(
                ResponseStatus::EXISTING_DATA,
                'A makeup session already exists for this absent week and session slot.'
            );
        }
    }

    private function formatMakeup(ScheduleMakeupSession $m): array
    {
        return [
            'id'                    => $m->id,
            'schedule_id'           => $m->schedule_id,
            'makeup_schedule_id'    => $m->makeup_schedule_id,
            'teacher_id'            => $m->teacher_id,
            'teacher_name'          => $m->teacher ? trim($m->teacher->first_name . ' ' . $m->teacher->last_name) : null,
            'subject_name'          => $m->schedule?->subject?->name,
            'original_shift_name'   => $m->schedule?->shift?->name,
            'makeup_slot_subject'   => $m->makeupSchedule?->subject?->name,
            'makeup_slot_shift'     => $m->makeupSchedule?->shift?->name,
            'shift_id'              => $m->shift_id,
            'shift_name'            => $m->makeupShift?->name,
            'makeup_date'           => $m->makeup_date?->toDateString(),
            'makeup_session'        => $m->makeup_session,
            'absent_week_number'    => $m->absent_week_number,
            'absent_date'           => $m->absent_date?->toDateString(),
            'attendance_status'     => $m->attendance_status,
            'status'                => $m->status,
            'note'                  => $m->note,
            'recorded_by'           => $m->recorder ? ($m->recorder->full_name ?? $m->recorder->username) : null,
            'created_at'            => $m->created_at?->toDateTimeString(),
        ];
    }
}
