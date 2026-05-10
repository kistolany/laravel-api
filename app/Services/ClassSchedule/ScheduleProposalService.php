<?php

namespace App\Services\ClassSchedule;

use App\Enums\ResponseStatus;
use App\Exceptions\ApiException;
use App\Models\ClassProgram;
use App\Models\ScheduleProposal;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleProposalService extends BaseService
{
    private const RELATIONS = ['classroom', 'subject', 'shift', 'teacher', 'sentBy', 'schedule'];
    private const WEEKEND_DAYS = ['Saturday', 'Sunday'];
    private const WEEKEND_SHIFT_RANGES = ['07:30-10:00', '11:00-14:30', '15:00-17:30'];

    public function __construct(
        private ClassScheduleService $scheduleService
    ) {}

    public function index(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            return ScheduleProposal::with(self::RELATIONS)
                ->orderByDesc('created_at')
                ->get()
                ->values();
        });
    }

    public function create(array $data, Authenticatable $sender): ScheduleProposal
    {
        return $this->trace(__FUNCTION__, function () use ($data, $sender): ScheduleProposal {
            $data = $this->withProgramContext($data);
            $proposal = ScheduleProposal::create([
                ...$data,
                'sent_by' => $sender->id,
                'status' => 'pending',
            ]);

            $proposal->load(self::RELATIONS);
            $this->notifyTeacher($proposal, $sender);

            return $proposal;
        });
    }

    public function resend(int $id, array $data, Authenticatable $sender): ScheduleProposal
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data, $sender): ScheduleProposal {
            $proposal = $this->findById($id);

            $newProposal = ScheduleProposal::create([
                'class_id' => $proposal->class_id,
                'subject_id' => $proposal->subject_id,
                'shift_id' => $proposal->shift_id,
                'day_of_week' => $proposal->day_of_week,
                'room' => $proposal->room,
                'academic_year' => $proposal->academic_year,
                'year_level' => $proposal->year_level,
                'semester' => $proposal->semester,
                'teacher_id' => $data['teacher_id'],
                'sent_by' => $sender->id,
                'status' => 'pending',
            ]);

            $newProposal->load(self::RELATIONS);
            $this->notifyTeacher($newProposal, $sender);

            return $newProposal;
        });
    }

    public function mine(?Authenticatable $user, ?int $teacherId = null): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($user, $teacherId): Collection {
            $teacherId = $teacherId ?: $this->resolveTeacherId($user);

            if (!$teacherId) {
                throw new ApiException(
                    ResponseStatus::BAD_REQUEST,
                    'Could not resolve teacher for this user. Make sure your account is linked to a teacher record.'
                );
            }

            return ScheduleProposal::with(self::RELATIONS)
                ->where('teacher_id', $teacherId)
                ->orderByDesc('created_at')
                ->get()
                ->values();
        });
    }

    public function respond(int $id, array $data): ScheduleProposal
    {
        return $this->trace(__FUNCTION__, function () use ($id, $data): ScheduleProposal {
            $proposal = $this->findById($id);

            if ($proposal->status !== 'pending') {
                throw new ApiException(ResponseStatus::BAD_REQUEST, 'This proposal has already been responded to.');
            }

            DB::transaction(function () use ($proposal, $data): void {
                $proposal->status = $data['status'];
                $proposal->reject_reason = $data['reject_reason'] ?? null;
                $proposal->responded_at = now();

                if ($data['status'] === 'accepted') {
                    $schedule = $this->scheduleService->create([
                        'class_id'   => $proposal->class_id,
                        'subject_id' => $proposal->subject_id,
                        'teacher_id' => $proposal->teacher_id,
                        'shift_id'   => $proposal->shift_id,
                        'day_of_week'=> $proposal->day_of_week,
                        'academic_year' => $proposal->academic_year,
                        'year_level'    => $proposal->year_level,
                        'semester'      => $proposal->semester,
                    ]);

                    $proposal->schedule_id = $schedule->id;
                }

                $proposal->save();
            });

            $proposal->load(self::RELATIONS);
            $this->notifyAdmin($proposal);

            return $proposal;
        });
    }

    public function delete(int $id): bool
    {
        return $this->trace(__FUNCTION__, function () use ($id): bool {
            return $this->findById($id)->delete();
        });
    }

    public function responseMessage(ScheduleProposal $proposal): string
    {
        return 'Response recorded' . ($proposal->status === 'accepted' ? ' and schedule created.' : '.');
    }

    private function findById(int $id): ScheduleProposal
    {
        $proposal = ScheduleProposal::with(self::RELATIONS)->find($id);

        if (!$proposal) {
            Log::warning('Schedule proposal not found.', ['id' => $id]);
            throw new ApiException(ResponseStatus::NOT_FOUND, "Schedule proposal with ID :$id not found.");
        }

        return $proposal;
    }

    private function withProgramContext(array $data): array
    {
        if (!empty($data['academic_year']) && !empty($data['year_level']) && !empty($data['semester'])) {
            return $data;
        }

        $program = ClassProgram::query()
            ->where('class_id', $data['class_id'] ?? null)
            ->when(($data['shift_id'] ?? null) && !$this->isWeekendScheduleBlock($data), fn ($query) => $query->where(fn ($q) => $q->whereNull('shift_id')->orWhere('shift_id', $data['shift_id'])))
            ->first();

        if (!$program) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'Please select a class program with academic year, year, and semester.'
            );
        }

        $data['academic_year'] = $data['academic_year'] ?? $program->academic_year;
        $data['year_level'] = $data['year_level'] ?? $program->year_level;
        $data['semester'] = $data['semester'] ?? $program->semester;

        if (empty($data['academic_year']) || empty($data['year_level']) || empty($data['semester'])) {
            throw new ApiException(
                ResponseStatus::BAD_REQUEST,
                'The selected class program is missing academic year, year, or semester.'
            );
        }

        return $data;
    }

    private function isWeekendScheduleBlock(array $data): bool
    {
        if (!in_array($data['day_of_week'] ?? null, self::WEEKEND_DAYS, true) || empty($data['shift_id'])) {
            return false;
        }

        $shift = Shift::find($data['shift_id']);

        return $shift ? in_array($this->normalizeTimeRange($shift->time_range), self::WEEKEND_SHIFT_RANGES, true) : false;
    }

    private function normalizeTimeRange(?string $value): string
    {
        $value = strtolower((string) $value);
        $value = str_replace([' ', '–', '—'], ['', '-', '-'], $value);

        return $value;
    }

    private function resolveTeacherId(?Authenticatable $user): ?int
    {
        $teacherId = $user?->teacher_id;

        if ($teacherId || !$user) {
            return $teacherId;
        }

        $teacher = Teacher::where('username', $user->username)
            ->orWhere('email', $user->username)
            ->first();

        if (!$teacher) {
            return null;
        }

        $user->forceFill(['teacher_id' => $teacher->id])->save();

        return $teacher->id;
    }

    private function notifyTeacher(ScheduleProposal $proposal, Authenticatable $sender): void
    {
        $senderName = $sender->full_name ?? $sender->username ?? 'Admin';
        $targetUserId = User::where('teacher_id', $proposal->teacher_id)->value('id');

        DB::table('push_notifications')->insert([
            'title' => 'New Schedule Proposal',
            'body' => "{$proposal->subject?->name} | {$proposal->day_of_week} | {$proposal->shift?->name} - sent by {$senderName}. Please confirm your availability.",
            'audience' => 'all',
            'priority' => 'info',
            'sent_by' => $sender->id,
            'target_user_id' => $targetUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function notifyAdmin(ScheduleProposal $proposal): void
    {
        $teacherName = $proposal->teacher ? trim($proposal->teacher->first_name . ' ' . $proposal->teacher->last_name) : 'Teacher';
        $statusText = $proposal->status === 'accepted' ? 'accepted' : 'rejected';
        $body = "{$teacherName} {$statusText} the proposal for {$proposal->subject?->name} | {$proposal->day_of_week} | {$proposal->shift?->name}.";

        if ($proposal->status === 'rejected' && $proposal->reject_reason) {
            $body .= " Reason: {$proposal->reject_reason}";
        }

        if ($proposal->status === 'accepted') {
            $body .= ' Schedule has been created automatically.';
        }

        DB::table('push_notifications')->insert([
            'title' => "Schedule Proposal {$statusText}",
            'body' => $body,
            'audience' => 'all',
            'priority' => $proposal->status === 'accepted' ? 'info' : 'warning',
            'sent_by' => null,
            'target_user_id' => $proposal->sent_by,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
