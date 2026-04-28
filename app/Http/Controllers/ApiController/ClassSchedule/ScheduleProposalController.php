<?php

namespace App\Http\Controllers\ApiController\ClassSchedule;

use App\Http\Controllers\Controller;
use App\Models\ClassSchedule;
use App\Models\ScheduleProposal;
use App\Models\Teacher;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleProposalController extends Controller
{
    use ApiResponseTrait;

    // ── Admin: list all proposals ─────────────────────────────────────────────
    public function index(): JsonResponse
    {
        $proposals = ScheduleProposal::with(['classroom', 'subject', 'shift', 'teacher', 'sentBy', 'schedule'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => $this->format($p));

        return $this->success($proposals->values()->all(), 'Proposals retrieved.');
    }

    // ── Admin: create + send proposal to a teacher ────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id'      => 'required|integer|exists:classes,id',
            'subject_id'    => 'required|integer|exists:subjects,id',
            'shift_id'      => 'required|integer|exists:shifts,id',
            'day_of_week'   => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'room'          => 'nullable|string|max:100',
            'academic_year' => 'required|string|max:20',
            'year_level'    => 'required|integer|min:1|max:6',
            'semester'      => 'required|integer|min:1|max:3',
            'teacher_id'    => 'required|integer|exists:teachers,id',
        ]);

        $proposal = ScheduleProposal::create([
            ...$data,
            'sent_by' => $request->user()->id,
            'status'  => 'pending',
        ]);

        $proposal->load(['classroom', 'subject', 'shift', 'teacher', 'sentBy']);

        // Notify teacher via push_notifications
        $this->notifyTeacher($proposal, $request->user());

        return $this->success($this->format($proposal), 'Proposal sent to teacher.', 201);
    }

    // ── Admin: resend a rejected proposal to a different teacher ─────────────
    public function resend(Request $request, int $id): JsonResponse
    {
        $proposal = ScheduleProposal::findOrFail($id);

        $data = $request->validate([
            'teacher_id' => 'required|integer|exists:teachers,id',
        ]);

        // Create a fresh proposal (keeps history of old rejection)
        $newProposal = ScheduleProposal::create([
            'class_id'      => $proposal->class_id,
            'subject_id'    => $proposal->subject_id,
            'shift_id'      => $proposal->shift_id,
            'day_of_week'   => $proposal->day_of_week,
            'room'          => $proposal->room,
            'academic_year' => $proposal->academic_year,
            'year_level'    => $proposal->year_level,
            'semester'      => $proposal->semester,
            'teacher_id'    => $data['teacher_id'],
            'sent_by'       => $request->user()->id,
            'status'        => 'pending',
        ]);

        $newProposal->load(['classroom', 'subject', 'shift', 'teacher', 'sentBy']);
        $this->notifyTeacher($newProposal, $request->user());

        return $this->success($this->format($newProposal), 'Proposal resent to new teacher.');
    }

    // ── Admin: delete a proposal ──────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        ScheduleProposal::findOrFail($id)->delete();
        return $this->success(null, 'Proposal deleted.');
    }

    // ── Teacher: list proposals sent to me ────────────────────────────────────
    public function mine(Request $request): JsonResponse
    {
        $teacherId = $request->query('teacher_id');

        // If no teacher_id param, derive it from the authenticated user's linked teacher record
        if (!$teacherId) {
            $user = $request->user();
            $teacherId = $user?->teacher_id;

            // If still null, try to find by username / email match in teachers table
            if (!$teacherId && $user) {
                $teacher = \App\Models\Teacher::where('username', $user->username)
                    ->orWhere('email', $user->username)
                    ->first();
                if ($teacher) {
                    $teacherId = $teacher->id;
                    // Link for future calls
                    $user->forceFill(['teacher_id' => $teacher->id])->save();
                }
            }
        }

        if (!$teacherId) {
            return $this->error('Could not resolve teacher for this user. Make sure your account is linked to a teacher record.', \App\Enums\ResponseStatus::BAD_REQUEST);
        }

        $proposals = ScheduleProposal::with(['classroom', 'subject', 'shift', 'sentBy', 'schedule'])
            ->where('teacher_id', $teacherId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($p) => $this->format($p));

        return $this->success($proposals->values()->all(), 'My proposals retrieved.');
    }

    // ── Teacher: respond to a proposal (accept / reject) ──────────────────────
    public function respond(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status'        => 'required|in:accepted,rejected',
            'reject_reason' => 'nullable|string|max:500',
        ]);

        $proposal = ScheduleProposal::with(['classroom', 'subject', 'shift', 'teacher'])->findOrFail($id);

        if ($proposal->status !== 'pending') {
            return $this->error('This proposal has already been responded to.', \App\Enums\ResponseStatus::BAD_REQUEST);
        }

        DB::transaction(function () use ($proposal, $data) {
            $proposal->status       = $data['status'];
            $proposal->reject_reason = $data['reject_reason'] ?? null;
            $proposal->responded_at  = now();

            if ($data['status'] === 'accepted') {
                // Auto-create the class schedule
                $schedule = ClassSchedule::create([
                    'class_id'      => $proposal->class_id,
                    'subject_id'    => $proposal->subject_id,
                    'teacher_id'    => $proposal->teacher_id,
                    'shift_id'      => $proposal->shift_id,
                    'day_of_week'   => $proposal->day_of_week,
                    'room'          => $proposal->room,
                    'academic_year' => $proposal->academic_year,
                    'year_level'    => $proposal->year_level,
                    'semester'      => $proposal->semester,
                ]);
                $proposal->schedule_id = $schedule->id;
            }

            $proposal->save();
        });

        $proposal->load(['classroom', 'subject', 'shift', 'teacher', 'sentBy', 'schedule']);

        // Notify admin
        $this->notifyAdmin($proposal);

        return $this->success($this->format($proposal), 'Response recorded' . ($data['status'] === 'accepted' ? ' and schedule created.' : '.'));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function format(ScheduleProposal $p): array
    {
        return [
            'id'            => $p->id,
            'class_id'      => $p->class_id,
            'class_name'    => $p->classroom?->name,
            'subject_id'    => $p->subject_id,
            'subject_name'  => $p->subject?->name,
            'shift_id'      => $p->shift_id,
            'shift_name'    => $p->shift?->name,
            'shift_time'    => $p->shift?->time_range,
            'day_of_week'   => $p->day_of_week,
            'room'          => $p->room,
            'academic_year' => $p->academic_year,
            'year_level'    => $p->year_level,
            'semester'      => $p->semester,
            'teacher_id'    => $p->teacher_id,
            'teacher_name'  => $p->teacher ? trim($p->teacher->first_name . ' ' . $p->teacher->last_name) : null,
            'sent_by'       => $p->sent_by,
            'sent_by_name'  => $p->sentBy?->full_name ?? $p->sentBy?->username,
            'status'        => $p->status,
            'reject_reason' => $p->reject_reason,
            'responded_at'  => $p->responded_at?->toDateTimeString(),
            'schedule_id'   => $p->schedule_id,
            'created_at'    => $p->created_at?->toDateTimeString(),
        ];
    }

    private function notifyTeacher(ScheduleProposal $p, $sender): void
    {
        $senderName = $sender->full_name ?? $sender->username ?? 'Admin';

        // Find the user account linked to this teacher so we can target them specifically
        $targetUserId = \App\Models\User::where('teacher_id', $p->teacher_id)->value('id');

        DB::table('push_notifications')->insert([
            'title'          => "📋 New Schedule Proposal",
            'body'           => "{$p->subject?->name} | {$p->day_of_week} | {$p->shift?->name} — sent by {$senderName}. Please confirm your availability.",
            'audience'       => 'all',           // fallback role filter (kept for compatibility)
            'priority'       => 'info',
            'sent_by'        => $sender->id,
            'target_user_id' => $targetUserId,   // NULL = link not found yet; still visible if teacher has same user id
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function notifyAdmin(ScheduleProposal $p): void
    {
        $teacherName = $p->teacher ? trim($p->teacher->first_name . ' ' . $p->teacher->last_name) : 'Teacher';
        $statusIcon  = $p->status === 'accepted' ? '✅' : '❌';
        $statusText  = $p->status === 'accepted' ? 'accepted' : 'rejected';

        $body = "{$teacherName} {$statusText} the proposal for {$p->subject?->name} | {$p->day_of_week} | {$p->shift?->name}.";
        if ($p->status === 'rejected' && $p->reject_reason) {
            $body .= " Reason: {$p->reject_reason}";
        }
        if ($p->status === 'accepted') {
            $body .= " Schedule has been created automatically.";
        }

        // Notify the admin who originally sent the proposal
        $targetUserId = $p->sent_by;

        DB::table('push_notifications')->insert([
            'title'          => "{$statusIcon} Schedule Proposal {$statusText}",
            'body'           => $body,
            'audience'       => 'all',
            'priority'       => $p->status === 'accepted' ? 'info' : 'warning',
            'sent_by'        => null,
            'target_user_id' => $targetUserId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
