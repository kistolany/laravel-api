<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Students;
use App\Models\Subject;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.secret' => str_repeat('testing-secret-key-', 4),
            'jwt.issuer' => 'http://localhost',
        ]);
    }

    public function test_teacher_can_list_majors(): void
    {
        $teacher = $this->createTeacherUser();
        $faculty = Faculty::create([
            'name' => 'Science',
        ]);

        Major::create([
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->issueToken($teacher))
            ->getJson('/api/v1/majors');

        $response
            ->assertOk()
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.items.0.name', 'Computer Science');
    }

    public function test_teacher_cannot_create_major(): void
    {
        $teacher = $this->createTeacherUser();
        $faculty = Faculty::create([
            'name' => 'Management',
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->issueToken($teacher))
            ->postJson('/api/v1/majors', [
                'faculty_id' => $faculty->id,
                'name' => 'Marketing',
            ]);

        $response->assertForbidden();
    }

    public function test_teacher_can_create_attendance_session(): void
    {
        $teacher = $this->createTeacherUser();
        [$class, $subject] = $this->createAttendanceContext();

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$this->issueToken($teacher))
            ->postJson('/api/v1/attendance-sessions', [
                'class_id' => $class->id,
                'subject_id' => $subject->id,
                'session_date' => '2026-03-10',
                'session_number' => 1,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.class_id', $class->id);
    }

    public function test_teacher_can_mark_and_update_attendance_records(): void
    {
        $teacher = $this->createTeacherUser();
        [$class, $subject] = $this->createAttendanceContext();
        $student = Students::create([
            'full_name_kh' => 'Student KH',
            'full_name_en' => 'Student One',
            'gender' => 'Male',
            'dob' => '2005-01-15',
            'phone' => '012345678',
            'email' => 'student1@example.com',
            'id_card_number' => 'ID-0001',
            'grade' => 'A',
            'short_docs_status' => false,
        ]);

        DB::table('class_students')->insert([
            'class_id' => $class->id,
            'student_id' => $student->id,
            'joined_date' => '2026-01-01',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = AttendanceSession::create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'session_date' => '2026-03-10',
            'session_number' => 1,
            'major_id' => $class->major_id,
            'shift_id' => $class->shift_id,
            'academic_year' => $class->academic_year,
            'year_level' => $class->year_level,
            'semester' => $class->semester,
        ]);

        $headers = ['Authorization' => 'Bearer '.$this->issueToken($teacher)];

        $this->withHeaders($headers)->postJson("/api/v1/attendance-sessions/{$session->id}/records", [
            'subject_id' => $subject->id,
            'session_date' => '2026-03-10',
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'Present',
                ],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance_records', [
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'Present',
        ]);

        $this->withHeaders($headers)->postJson("/api/v1/attendance-sessions/{$session->id}/records", [
            'subject_id' => $subject->id,
            'session_date' => '2026-03-10',
            'records' => [
                [
                    'student_id' => $student->id,
                    'status' => 'Late',
                ],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance_records', [
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'Late',
        ]);
    }

    private function createTeacherUser(): User
    {
        $role = Role::create([
            'name' => 'Teacher',
            'description' => 'Teacher attendance and major listing access',
        ]);
        $permissions = collect(['major.view', 'attendance.create', 'attendance.record'])
            ->map(fn (string $name) => Permission::create(['name' => $name]));

        $role->permissions()->attach($permissions->pluck('id')->all());

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function issueToken(User $user): string
    {
        return app(JwtService::class)->issueAccessToken($user)['token'];
    }

    private function createAttendanceContext(): array
    {
        $faculty = Faculty::create([
            'name' => 'Science',
        ]);
        $major = Major::create([
            'faculty_id' => $faculty->id,
            'name' => 'Computer Science',
        ]);
        $shift = Shift::create([
            'name' => 'Morning',
            'time_range' => '08:00-11:00',
        ]);
        $subject = Subject::create([
            'subject_Code' => 'CS101',
            'name' => 'Intro to CS',
        ]);
        $class = Classes::create([
            'name' => 'CS1-M1',
            'major_id' => $major->id,
            'shift_id' => $shift->id,
            'academic_year' => '2025-2026',
            'year_level' => 1,
            'semester' => 1,
            'section' => 'A',
            'max_students' => 40,
            'is_active' => true,
        ]);

        return [$class, $subject];
    }
}
