<?php

namespace Tests\Feature;

use App\Models\AcademicInfo;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Shift;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LookupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_lookup_majors_returns_all_records_when_faculty_filter_is_missing(): void
    {
        $science = Faculty::create([
            'name' => 'Science',
        ]);

        $management = Faculty::create([
            'name' => 'Management',
        ]);

        Major::create([
            'faculty_id' => $science->id,
            'name' => 'Computer Science',
        ]);

        Major::create([
            'faculty_id' => $management->id,
            'name' => 'Accounting',
        ]);

        $response = $this->getJson('/api/v1/lookups/majors');

        $response
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Accounting'])
            ->assertJsonFragment(['name' => 'Computer Science']);
    }

    public function test_lookup_majors_filters_records_by_faculty_id(): void
    {
        $science = Faculty::create([
            'name' => 'Science',
        ]);

        $management = Faculty::create([
            'name' => 'Management',
        ]);

        Major::create([
            'faculty_id' => $science->id,
            'name' => 'Computer Science',
        ]);

        Major::create([
            'faculty_id' => $management->id,
            'name' => 'Accounting',
        ]);

        $response = $this->getJson("/api/v1/lookups/majors?faculty_id={$science->id}");

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Computer Science');
    }

    public function test_lookup_subjects_returns_all_records_when_major_filter_is_missing(): void
    {
        Subject::create([
            'subject_Code' => 'ACC101',
            'name' => 'Accounting',
        ]);

        Subject::create([
            'subject_Code' => 'CS101',
            'name' => 'Computer Science',
        ]);

        $response = $this->getJson('/api/v1/lookups/subjects');

        $response
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Accounting'])
            ->assertJsonFragment(['name' => 'Computer Science']);
    }

    public function test_lookup_subjects_filters_records_by_major_id(): void
    {
        $science = Faculty::create([
            'name' => 'Science',
        ]);

        $management = Faculty::create([
            'name' => 'Management',
        ]);

        $computerScience = Major::create([
            'faculty_id' => $science->id,
            'name' => 'Computer Science',
        ]);

        $accounting = Major::create([
            'faculty_id' => $management->id,
            'name' => 'Accounting',
        ]);

        $algorithms = Subject::create([
            'subject_Code' => 'CS201',
            'name' => 'Algorithms',
        ]);

        $finance = Subject::create([
            'subject_Code' => 'FIN101',
            'name' => 'Finance',
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
            ->assertJsonPath('data.0.name', 'Algorithms');
    }

    public function test_lookup_score_filters_returns_all_final_result_filter_options(): void
    {
        $faculty = Faculty::create(['name' => 'Technology']);
        $major = Major::create([
            'faculty_id' => $faculty->id,
            'name' => 'Information Technology',
        ]);
        $shift = Shift::create([
            'name' => 'Morning',
            'time_range' => '8:00 - 11:00',
        ]);

        AcademicInfo::create([
            'student_id' => 'student-1',
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'batch_year' => 2026,
            'stage' => 'Year 2',
            'study_days' => 'Weekday',
        ]);

        Classes::create([
            'name' => 'IT Year 2 Semester 2',
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'academic_year' => '2026',
            'year_level' => 2,
            'semester' => 2,
            'section' => 'A',
            'max_students' => 30,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/lookups/score-filters');

        $response
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonStructure([
                'data' => [
                    'stages',
                    'batch-years',
                    'semesters',
                    'academic-years',
                    'faculties',
                    'majors',
                    'shifts',
                ],
            ])
            ->assertJsonFragment(['value' => 'Year 2', 'label' => 'Year 2'])
            ->assertJsonFragment(['value' => '2026', 'label' => '2026'])
            ->assertJsonFragment(['value' => '2', 'label' => '2'])
            ->assertJsonFragment(['id' => $faculty->id, 'name' => 'Technology'])
            ->assertJsonFragment(['id' => $major->id, 'faculty_id' => $faculty->id, 'name' => 'Information Technology'])
            ->assertJsonFragment(['id' => $shift->id, 'name' => 'Morning', 'time_range' => '8:00 - 11:00']);
    }
}
