<?php

namespace App\Services;

use App\Models\Students;
use Carbon\CarbonImmutable;

class StudentCardService
{
    public function buildStudentCardResponse(int $studentId): array
    {
        $student = Students::with(['academicInfo.major'])->find($studentId);

        if (!$student) {
            return [
                'status' => 404,
                'payload' => [
                    'message' => "Student with ID {$studentId} not found.",
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => $this->buildCardPayload($student, CarbonImmutable::now()),
        ];
    }

    public function buildMajorCardsResponse(string $major): array
    {
        $query = Students::with(['academicInfo.major']);

        if (ctype_digit($major)) {
            $majorId = (int) $major;
            $query->whereHas('academicInfo', function ($q) use ($majorId) {
                $q->where('major_id', $majorId);
            });
        } else {
            $query->whereHas('academicInfo.major', function ($q) use ($major) {
                $q->where('name_eg', $major)
                    ->orWhere('name_kh', $major);
            });
        }

        $issuedDate = CarbonImmutable::now();

        $cards = $query->get()->map(function ($student) use ($issuedDate) {
            return $this->buildCardPayload($student, $issuedDate);
        })->all();

        if (empty($cards)) {
            return [
                'status' => 404,
                'payload' => [
                    'message' => 'No students found for the provided major.',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => $cards,
        ];
    }

    private function buildCardPayload(Students $student, CarbonImmutable $issuedDate): array
    {
        $major = $student->academicInfo?->major;

        return [
            'FullName_en' => $student->full_name_en ?? '',
            'FullName_kh' => $student->full_name_kh ?? '',
            'Major_en' => $major?->name_eg ?? '',
            'Major_kh' => $major?->name_kh ?? '',
            'barcode' => $student->barcode,
            'ISS' => $issuedDate->format('Y-m-d'),
            'EXP' => $issuedDate->addYear()->format('Y-m-d'),
        ];
    }
}
