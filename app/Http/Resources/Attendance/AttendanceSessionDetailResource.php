<?php

namespace App\Http\Resources\Attendance;

use App\DTOs\AttendanceSessionDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class AttendanceSessionDetailResource extends JsonResource
{
    /** @var AttendanceSessionDetail */
    public $resource;

    public function toArray(Request $request): array
    {
        $session = $this->resource->session;
        $classroom = $session->classroom;
        $major = $session->major ?? $classroom?->major;
        $subject = $session->subject;
        $shift = $session->shift ?? $classroom?->shift;

        return [
            'id' => $session->id,
            'academic_year' => $session->academic_year ?? $classroom?->academic_year,
            'year_level' => $session->year_level ?? $classroom?->year_level,
            'semester' => $session->semester ?? $classroom?->semester,
            'session_date' => $this->formatDate($session->session_date),
            'session_number' => $session->session_number,
            'class' => [
                'id' => $classroom?->id,
                'code' => $classroom?->code,
                'section' => $classroom?->section,
            ],
            'major' => [
                'id' => $major?->id,
                'name_en' => $major?->name_eg,
                'name_kh' => $major?->name_kh,
            ],
            'subject' => [
                'id' => $subject?->id,
                'code' => $subject?->subject_Code,
                'name_en' => $subject?->name_eg,
            ],
            'shift' => [
                'id' => $shift?->id,
                'name_en' => $shift?->name_en,
                'time_range' => $shift?->time_range,
            ],
            'students' => $this->resource->students,
            'summary' => $this->resource->summary,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }
}


