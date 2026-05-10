<?php

namespace App\Http\Resources\Teacher;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherAttendanceHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalRecords = $this->resource->getAttribute('total_records');
        $presentCount = $this->resource->getAttribute('present_count');
        $absentCount = $this->resource->getAttribute('absent_count');
        $lateCount = $this->resource->getAttribute('late_count');
        $excusedCount = $this->resource->getAttribute('excused_count');

        if ($totalRecords !== null) {
            $summary = [
                'present' => (int) $presentCount,
                'absent' => (int) $absentCount,
                'late' => (int) $lateCount,
                'excused' => (int) $excusedCount,
                'total_records' => (int) $totalRecords,
            ];
        } else {
            $summary = [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'excused' => 0,
                'total_records' => $this->records->count(),
            ];

            foreach ($this->records as $record) {
                $status = strtolower((string) $record->status);

                switch ($status) {
                    case 'present':
                        $summary['present']++;
                        break;
                    case 'late':
                        $summary['late']++;
                        break;
                    case 'excused':
                        $summary['excused']++;
                        break;
                    default:
                        $summary['absent']++;
                        break;
                }
            }
        }

        return [
            'id' => $this->id,
            'session_date' => optional($this->session_date)->format('Y-m-d'),
            'session_number' => $this->session_number,
            'academic_year' => $this->academic_year,
            'year_level' => $this->year_level,
            'semester' => $this->semester,
            'class' => $this->classroom ? [
                'id'   => $this->classroom->id,
                'name' => $this->classroom->name,
            ] : null,
            'major' => $this->major ? [
                'id' => $this->major->id,
                'name_en' => $this->major->name_eg,
                'name_kh' => $this->major->name_kh,
            ] : null,
            'subject' => $this->subject ? [
                'id' => $this->subject->id,
                'code' => $this->subject->subject_Code,
                'name_en' => $this->subject->name_eg,
                'name_kh' => $this->subject->name_kh,
            ] : null,
            'summary' => $summary,
        ];
    }
}


