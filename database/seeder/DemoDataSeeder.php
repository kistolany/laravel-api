<?php

namespace Database\Seeders;

use App\Models\AcademicInfo;
use App\Models\Address;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Classes;
use App\Models\ClassProgram;
use App\Models\ClassSchedule;
use App\Models\ClassStudent;
use App\Models\Commune;
use App\Models\District;
use App\Models\Faculty;
use App\Models\Major;
use App\Models\ParentGuardian;
use App\Models\Province;
use App\Models\Role;
use App\Models\Scholarship;
use App\Models\Shift;
use App\Models\Students;
use App\Models\StudentScore;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles (Ensure they exist)
        $roles = ['Admin', 'Staff', 'Assistant', 'Teacher', 'OrderStaff', 'Viewer'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
        $staffRole = Role::where('name', 'Staff')->first();

        // 2. Users (5)
        for ($i = 1; $i <= 5; $i++) {
            User::updateOrCreate(
                ['username' => "staff$i"],
                [
                    'password_hash' => Hash::make('123456'),
                    'role_id' => $staffRole->id,
                    'status' => 'Active',
                    'full_name' => "Demo Staff $i",
                    'phone' => "01234567$i",
                ]
            );
        }

        // 3. Faculties & Majors
        $faculties = ['Applied Science', 'Business & Management', 'Arts & Humanities'];
        foreach ($faculties as $fName) {
            $faculty = Faculty::firstOrCreate(['name' => $fName]);
            Major::firstOrCreate(['name' => "Major for $fName", 'faculty_id' => $faculty->id]);
        }
        $major = Major::first();

        // 4. Subjects (5)
        $subjects = ['Introduction to IT', 'Mathematic I', 'English for Academic', 'Web Development', 'Business Ethics'];
        foreach ($subjects as $sName) {
            Subject::firstOrCreate(
                ['name' => $sName],
                ['subject_Code' => Str::upper(Str::random(5))]
            );
        }
        $subject = Subject::first();

        // 5. Shifts
        $shifts = [
            ['name' => 'Morning', 'time_range' => '07:30 - 11:00'],
            ['name' => 'Afternoon', 'time_range' => '13:30 - 17:00'],
            ['name' => 'Evening', 'time_range' => '17:30 - 20:30'],
            ['name' => 'Weekend', 'time_range' => '08:00 - 16:30'],
        ];
        foreach ($shifts as $sData) {
            Shift::firstOrCreate(['name' => $sData['name']], $sData);
        }
        $shift = Shift::first();

        // 6. Province, District, Commune (Base Address Data)
        $province = Province::firstOrCreate(['name' => 'Phnom Penh']);
        $district = District::firstOrCreate(['name' => 'Daun Penh', 'province_id' => $province->id]);
        $commune = Commune::firstOrCreate(['name' => 'Phsar Kandal', 'district_id' => $district->id]);

        // 7. Teachers (5)
        for ($i = 1; $i <= 5; $i++) {
            Teacher::updateOrCreate(
                ['username' => "teacher$i"],
                [
                    'teacher_id' => "T" . str_pad($i, 5, '0', STR_PAD_LEFT),
                    'first_name' => "Teacher",
                    'last_name' => "$i",
                    'gender' => $i % 2 == 0 ? 'Female' : 'Male',
                    'dob' => '1985-01-01',
                    'nationality' => 'Khmer',
                    'email' => "teacher$i@example.com",
                    'password' => Hash::make('123456'),
                    'phone_number' => "088123456$i",
                    'major_id' => $major->id,
                    'subject_id' => $subject->id,
                    'join_date' => now(),
                    'address' => 'Phnom Penh, Cambodia',
                ]
            );
        }
        $teacher = Teacher::first();

        // 8. Students (5)
        $students = [];
        for ($i = 1; $i <= 5; $i++) {
            $student = Students::updateOrCreate(
                ['full_name_en' => "STUDENT $i"],
                [
                    'full_name_kh' => "សិស្ស ទី$i",
                    'gender' => $i % 2 == 0 ? 'Female' : 'Male',
                    'dob' => '2005-05-20',
                    'phone' => "09988776$i",
                    'email' => "student$i@example.com",
                    'id_card_number' => "ID8877$i",
                    'student_type' => 'PAY',
                    'status' => 'active',
                    'bacll_code' => "B" . str_pad($i, 5, '0', STR_PAD_LEFT),
                    'grade' => 'A',
                    'doc' => now()->toDateString(),
                ]
            );
            $students[] = $student;

            Address::updateOrCreate(
                ['student_id' => $student->id, 'address_type' => 'Current'],
                [
                    'province_id' => $province->id,
                    'district_id' => $district->id,
                    'commune_id' => $commune->id,
                    'village' => 'Village ' . $i,
                ]
            );

            AcademicInfo::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'major_id' => $major->id,
                    'shift_id' => $shift->id,
                    'batch_year' => '2025',
                    'stage' => 'Year 1',
                    'study_days' => 'Mon-Fri',
                ]
            );

            ParentGuardian::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'father_name' => 'Father ' . $i,
                    'father_job' => 'Farmer',
                    'mother_name' => 'Mother ' . $i,
                    'mother_job' => 'Housewife',
                    'guardian_name' => 'Guardian ' . $i,
                    'guardian_job' => 'Merchant',
                    'guardian_phone' => '01122334' . $i,
                ]
            );

            Scholarship::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'nationality' => 'Khmer',
                    'ethnicity' => 'Khmer',
                    'emergency_name' => 'Emergency Contact ' . $i,
                    'emergency_relation' => 'Relative',
                    'emergency_phone' => '088112233' . $i,
                    'emergency_address' => 'Phnom Penh',
                    'grade' => 'A',
                    'exam_year' => '2023',
                ]
            );
        }

        // 9. Classes (5)
        $classes = [];
        for ($i = 1; $i <= 5; $i++) {
            $cls = Classes::updateOrCreate(
                ['name' => "ClassName $i"],
                [
                    'major_id' => $major->id,
                    'shift_id' => $shift->id,
                    'academic_year' => '2025-2026',
                    'year_level' => 1,
                    'semester' => 1,
                    'section' => 'A',
                    'max_students' => 30,
                    'is_active' => true,
                ]
            );
            $classes[] = $cls;

            ClassProgram::updateOrCreate(
                ['class_id' => $cls->id],
                ['name' => 'Bachelor Program', 'description' => 'Demo Description']
            );

            ClassSchedule::updateOrCreate(
                ['class_id' => $cls->id, 'day_of_week' => 'Monday'],
                ['start_time' => '07:30', 'end_time' => '11:00', 'room' => 'Room ' . $i]
            );
        }

        // 10. Enroll Students into Classes
        foreach ($students as $index => $stu) {
            $cls = $classes[$index % count($classes)];
            ClassStudent::updateOrCreate(
                ['class_id' => $cls->id, 'student_id' => $stu->id],
                ['joined_date' => now(), 'status' => 'Active']
            );

            // 11. Student Scores
            StudentScore::updateOrCreate(
                ['student_id' => $stu->id, 'class_id' => $cls->id, 'subject_id' => $subject->id],
                [
                    'academic_year' => '2025-2026',
                    'year_level' => 1,
                    'semester' => 1,
                    'class_score' => 10,
                    'assignment_score' => 20,
                    'midterm_score' => 25,
                    'final_score' => 40,
                ]
            );
        }

        // 12. Attendance Sessions & Records
        foreach ($classes as $cls) {
            $session = AttendanceSession::updateOrCreate(
                ['class_id' => $cls->id, 'subject_id' => $subject->id, 'session_date' => now()->toDateString(), 'session_number' => 1],
                [
                    'teacher_id' => $teacher->id,
                    'major_id' => $major->id,
                    'shift_id' => $shift->id,
                    'academic_year' => '2025-2026',
                    'year_level' => 1,
                    'semester' => 1,
                ]
            );

            foreach ($cls->students as $stu) {
                AttendanceRecord::updateOrCreate(
                    ['attendance_session_id' => $session->id, 'student_id' => $stu->id],
                    ['status' => 'Present']
                );
            }
        }

        // 13. Teacher Attendance
        foreach (Teacher::all() as $t) {
            TeacherAttendance::updateOrCreate(
                ['teacher_id' => $t->id, 'attendance_date' => now()->toDateString()],
                [
                    'status' => 'Present',
                    'check_in_time' => '07:30',
                    'check_out_time' => '11:00',
                    'recorded_by' => User::first()->id,
                ]
            );
        }

        // 14. Extra Students for Sorting (PAY/PASS without classes) and Pending (Scholarship)
        $extraTypes = [
            ['type' => 'PAY',     'name' => 'WAITING PAYER A'],
            ['type' => 'PAY',     'name' => 'WAITING PAYER B'],
            ['type' => 'PASS',    'name' => 'WAITING SCHOLAR A'],
            ['type' => 'PENDING', 'name' => 'PENDING CANDIDATE A'],
            ['type' => 'PENDING', 'name' => 'PENDING CANDIDATE B'],
        ];

        foreach ($extraTypes as $index => $data) {
            $student = Students::updateOrCreate(
                ['full_name_en' => $data['name']],
                [
                    'full_name_kh' => "និស្សិតរង់ចាំ " . ($index + 1),
                    'gender' => $index % 2 == 0 ? 'Female' : 'Male',
                    'dob' => '2006-10-10',
                    'phone' => "0128899" . $index,
                    'email' => "waiting" . $index . "@example.com",
                    'id_card_number' => "ID8899" . $index,
                    'student_type' => $data['type'],
                    'status' => 'active',
                    'bacll_code' => "B" . str_pad($index + 20, 5, '0', STR_PAD_LEFT),
                    'grade' => 'C',
                    'doc' => now()->toDateString(),
                ]
            );

            Address::updateOrCreate(
                ['student_id' => $student->id, 'address_type' => 'Current'],
                [
                    'province_id' => $province->id,
                    'district_id' => $district->id,
                    'commune_id' => $commune->id,
                    'village' => 'Village ' . $index,
                ]
            );

            AcademicInfo::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'major_id' => $major->id,
                    'shift_id' => $shift->id,
                    'batch_year' => '2025',
                    'stage' => 'Year 1',
                    'study_days' => 'Mon-Fri',
                ]
            );
        }
    }
}
