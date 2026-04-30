<?php

namespace App\Services\Teacher;

use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Services\Concerns\ServiceTraceable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherAvailabilityService
{
    use ServiceTraceable;

    public function list(?Authenticatable $user, ?string $guard): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($user, $guard): Collection {
            $query = TeacherAvailability::with(['teacher', 'subject', 'shift']);

            if ($guard === 'teacher') {
                $query->where('teacher_id', $user?->id);
            }

            return $query->get()->values();
        });
    }

    public function byTeacher(int $teacherId): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($teacherId): Collection {
            return TeacherAvailability::with(['subject', 'shift'])
                ->where('teacher_id', $teacherId)
                ->get()
                ->values();
        });
    }

    public function summary(): Collection
    {
        return $this->trace(__FUNCTION__, function (): Collection {
            $teachers = Teacher::with(['subject'])
                ->orderBy('first_name')
                ->get();

            $availabilities = TeacherAvailability::with(['subject', 'shift'])
                ->get()
                ->groupBy('teacher_id');

            return $teachers->map(function (Teacher $teacher) use ($availabilities): array {
                return [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => trim($teacher->first_name . ' ' . $teacher->last_name),
                    'subject' => $teacher->subject ? [
                        'id' => $teacher->subject->id,
                        'name' => $teacher->subject->name,
                    ] : null,
                    'availability' => $availabilities->get($teacher->id, collect())->values(),
                ];
            })->values();
        });
    }

    public function sync(array $data): Collection
    {
        return $this->trace(__FUNCTION__, function () use ($data): Collection {
            DB::transaction(function () use ($data): void {
                TeacherAvailability::where('teacher_id', $data['teacher_id'])->delete();

                $rows = $this->buildUniqueRows($data);

                if ($rows !== []) {
                    TeacherAvailability::insert($rows);
                }
            });

            return $this->byTeacher((int) $data['teacher_id']);
        });
    }

    private function buildUniqueRows(array $data): array
    {
        $now = now();

        return collect($data['slots'])
            ->map(fn (array $slot): array => [
                'teacher_id' => $data['teacher_id'],
                'subject_id' => $slot['subject_id'],
                'shift_id' => $slot['shift_id'],
                'day_of_week' => $slot['day_of_week'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique(fn (array $row): string => $row['subject_id'] . '-' . $row['shift_id'] . '-' . $row['day_of_week'])
            ->values()
            ->all();
    }
}
