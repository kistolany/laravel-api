<?php

namespace Tests\Feature;

use App\Models\AcademicInfo;
use App\Models\Classes;
use App\Models\ClassProgram;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\MajorSubject;
use App\Models\Shift;
use App\Models\Students;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    public function test_attendance_class_lookup_returns_only_classes_with_matching_active_students(): void
    {
        $faculty = Faculty::create(['name' => 'Applied Science']);
        $otherFaculty = Faculty::create(['name' => 'Business']);
        $major = Major::create(['faculty_id' => $faculty->id, 'name' => 'Web Development']);
        $otherMajor = Major::create(['faculty_id' => $otherFaculty->id, 'name' => 'Accounting']);
        $shift = Shift::create(['name' => 'Morning', 'time_range' => '08:00-11:00']);

        $directClass = Classes::create([
            'name' => 'A2',
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'academic_year' => '2026',
            'year_level' => 2,
            'semester' => 1,
        ]);

        $programClass = Classes::create([
            'name' => 'Shared Program',
            'academic_year' => '2026',
        ]);

        ClassProgram::create([
            'class_id' => $programClass->id,
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'year_level' => 2,
            'semester' => 1,
        ]);

        $otherClass = Classes::create([
            'name' => 'B1',
            'major_id' => $otherMajor->id,
            'shift_id' => $shift->id,
            'academic_year' => '2026',
            'year_level' => 2,
            'semester' => 1,
        ]);

        $emptyClass = Classes::create([
            'name' => 'Empty Match',
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'academic_year' => '2026',
            'year_level' => 2,
            'semester' => 1,
        ]);

        $student = $this->createStudentWithAcademicInfo($major->id, $shift->id, 2026, 'Year 2', 'Day');
        $otherStudent = $this->createStudentWithAcademicInfo($otherMajor->id, $shift->id, 2026, 'Year 2', 'Day');

        $this->attachStudent($directClass->id, $student->id);
        $this->attachStudent($programClass->id, $student->id);
        $this->attachStudent($otherClass->id, $otherStudent->id);

        $response = $this->getJson('/api/v1/lookups/attendance-classes?' . http_build_query([
            'faculty_id' => $faculty->id,
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'year_level' => 2,
            'semester' => 1,
            'batch_year' => 2026,
            'academic_year' => '2026',
            'study_day' => 'Day',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $directClass->id, 'name' => 'A2'])
            ->assertJsonFragment(['id' => $programClass->id, 'name' => 'Shared Program'])
            ->assertJsonMissing(['id' => $otherClass->id, 'name' => 'B1'])
            ->assertJsonMissing(['id' => $emptyClass->id, 'name' => 'Empty Match']);
    }

    public function test_attendance_subject_lookup_returns_names_for_selected_class_context(): void
    {
        $faculty = Faculty::create(['name' => 'Applied Science']);
        $major = Major::create(['faculty_id' => $faculty->id, 'name' => 'Web Development']);
        $otherMajor = Major::create(['faculty_id' => $faculty->id, 'name' => 'Data']);
        $shift = Shift::create(['name' => 'Morning', 'time_range' => '08:00-11:00']);
        $class = Classes::create(['name' => 'A2', 'academic_year' => '2026']);

        ClassProgram::create([
            'class_id' => $class->id,
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'year_level' => 2,
            'semester' => 1,
        ]);

        $web = Subject::create(['subject_Code' => 'WEB101', 'name' => 'Web']);
        $database = Subject::create(['subject_Code' => 'DB101', 'name' => 'Database']);

        MajorSubject::create([
            'major_id' => $major->id,
            'subject_id' => $web->id,
            'year_level' => 2,
            'semester' => 1,
        ]);

        MajorSubject::create([
            'major_id' => $otherMajor->id,
            'subject_id' => $database->id,
            'year_level' => 2,
            'semester' => 1,
        ]);

        $response = $this->getJson('/api/v1/lookups/attendance-subjects?' . http_build_query([
            'class_id' => $class->id,
            'major_id' => $major->id,
            'year_level' => 2,
            'semester' => 1,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['id' => $web->id, 'name' => 'Web'])
            ->assertJsonMissing(['subject_Code' => 'WEB101'])
            ->assertJsonMissing(['id' => $database->id, 'name' => 'Database']);
    }

    private function createStudentWithAcademicInfo(int $majorId, int $shiftId, int $batchYear, string $stage, string $studyDays): Students
    {
        $student = Students::create([
            'full_name_kh' => 'Student KH ' . uniqid(),
            'full_name_en' => 'Student EN ' . uniqid(),
            'gender' => 'Male',
            'dob' => '2005-01-01',
            'phone' => '012345678',
            'email' => null,
            'id_card_number' => 'ID-' . uniqid(),
            'grade' => 'A',
            'short_docs_status' => false,
        ]);

        AcademicInfo::create([
            'student_id' => (string) $student->id,
            'major_id' => $majorId,
            'shift_id' => $shiftId,
            'batch_year' => $batchYear,
            'stage' => $stage,
            'study_days' => $studyDays,
        ]);

        return $student;
    }

    private function attachStudent(int $classId, int $studentId): void
    {
        DB::table('class_students')->insert([
            'class_id' => $classId,
            'student_id' => $studentId,
            'joined_date' => '2026-01-01',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
