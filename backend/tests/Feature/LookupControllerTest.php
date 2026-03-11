<?php

namespace Tests\Feature;

use App\Models\Faculty;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_majors_returns_all_records_when_faculty_filter_is_missing(): void
    {
        $science = Faculty::create([
            'name_kh' => 'Science KH',
            'name_eg' => 'Science',
        ]);

        $management = Faculty::create([
            'name_kh' => 'Management KH',
            'name_eg' => 'Management',
        ]);

        Major::create([
            'faculty_id' => $science->id,
            'name_kh' => 'Computer Science KH',
            'name_eg' => 'Computer Science',
        ]);

        Major::create([
            'faculty_id' => $management->id,
            'name_kh' => 'Accounting KH',
            'name_eg' => 'Accounting',
        ]);

        $response = $this->getJson('/api/v1/lookups/majors');

        $response
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name_eg' => 'Accounting'])
            ->assertJsonFragment(['name_eg' => 'Computer Science']);
    }

    public function test_lookup_majors_filters_records_by_faculty_id(): void
    {
        $science = Faculty::create([
            'name_kh' => 'Science KH',
            'name_eg' => 'Science',
        ]);

        $management = Faculty::create([
            'name_kh' => 'Management KH',
            'name_eg' => 'Management',
        ]);

        Major::create([
            'faculty_id' => $science->id,
            'name_kh' => 'Computer Science KH',
            'name_eg' => 'Computer Science',
        ]);

        Major::create([
            'faculty_id' => $management->id,
            'name_kh' => 'Accounting KH',
            'name_eg' => 'Accounting',
        ]);

        $response = $this->getJson("/api/v1/lookups/majors?faculty_id={$science->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_eg', 'Computer Science');
    }

    public function test_lookup_subjects_returns_all_records_when_major_filter_is_missing(): void
    {
        Subject::create([
            'subject_Code' => 'ACC101',
            'name_kh' => 'Accounting KH',
            'name_eg' => 'Accounting',
        ]);

        Subject::create([
            'subject_Code' => 'CS101',
            'name_kh' => 'Computer Science KH',
            'name_eg' => 'Computer Science',
        ]);

        $response = $this->getJson('/api/v1/lookups/subjects');

        $response
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name_eg' => 'Accounting'])
            ->assertJsonFragment(['name_eg' => 'Computer Science']);
    }

    public function test_lookup_subjects_filters_records_by_major_id(): void
    {
        $science = Faculty::create([
            'name_kh' => 'Science KH',
            'name_eg' => 'Science',
        ]);

        $management = Faculty::create([
            'name_kh' => 'Management KH',
            'name_eg' => 'Management',
        ]);

        $computerScience = Major::create([
            'faculty_id' => $science->id,
            'name_kh' => 'Computer Science KH',
            'name_eg' => 'Computer Science',
        ]);

        $accounting = Major::create([
            'faculty_id' => $management->id,
            'name_kh' => 'Accounting KH',
            'name_eg' => 'Accounting',
        ]);

        $algorithms = Subject::create([
            'subject_Code' => 'CS201',
            'name_kh' => 'Algorithms KH',
            'name_eg' => 'Algorithms',
        ]);

        $finance = Subject::create([
            'subject_Code' => 'FIN101',
            'name_kh' => 'Finance KH',
            'name_eg' => 'Finance',
        ]);

        MajorSubject::create([
            'major_id' => $computerScience->id,
            'subject_id' => $algorithms->id,
            'year_level' => 2,
            'semester' => 1,
        ]);

        MajorSubject::create([
            'major_id' => $accounting->id,
            'subject_id' => $finance->id,
            'year_level' => 1,
            'semester' => 1,
        ]);

        $response = $this->getJson("/api/v1/lookups/subjects?major_id={$computerScience->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_eg', 'Algorithms');
    }
}
