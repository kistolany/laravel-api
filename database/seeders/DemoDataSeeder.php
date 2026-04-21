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
        $subjects_list = ['Introduction to IT', 'Mathematic I', 'English for Academic', 'Web Development', 'Business Ethics'];
        foreach ($subjects_list as $sName) {
            Subject::firstOrCreate(
                ['name' => $sName],
                ['subject_Code' => Str::upper(Str::random(5))]
            );
        }

        // 5. Shifts
        $shifts_list = [
            ['name' => 'Morning', 'time_range' => '07:30 - 11:00'],
            ['name' => 'Afternoon', 'time_range' => '13:30 - 17:00'],
            ['name' => 'Evening', 'time_range' => '17:30 - 20:30'],
            ['name' => 'Weekend', 'time_range' => '08:00 - 16:30'],
        ];
        foreach ($shifts_list as $sData) {
            Shift::firstOrCreate(['name' => $sData['name']], $sData);
        }

        // 6. Province, District, Commune (Base Address Data)
        $province = Province::firstOrCreate(['name' => 'Phnom Penh']);
        $district = District::firstOrCreate(['name' => 'Daun Penh', 'province_id' => $province->id]);
        $commune = Commune::firstOrCreate(['name' => 'Phsar Kandal', 'district_id' => $district->id]);

        // 7. Teachers (5)
        $all_subjects = Subject::all();
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
                    'subject_id' => $all_subjects[$i % count($all_subjects)]->id,
                    'is_verified' => true,
                    'join_date' => now(),
                    'address' => 'Phnom Penh, Cambodia',
                ]
            );
        }

        // Fetch IDs for diversification
        $teacherIds = Teacher::pluck('id')->toArray();
        $subjectIds = Subject::pluck('id')->toArray();
        $shiftIds   = Shift::pluck('id')->toArray();

        // 8. Students (10 total: 4 PAY, 3 PASS, 3 PENDING)
        $students = [];
        $types = ['PAY', 'PAY', 'PAY', 'PAY', 'PASS', 'PASS', 'PASS', 'PENDING', 'PENDING', 'PENDING'];
        
        for ($i = 0; $i < 10; $i++) {
            $type = $types[$i];
            $num = $i + 1;
            $student_shift_id = $shiftIds[$i % count($shiftIds)];
            
            $student = Students::updateOrCreate(
                ['full_name_en' => "STUDENT $num"],
                [
                    'full_name_kh' => "សិស្ស ទី$num",
                    'gender' => $num % 2 == 0 ? 'Female' : 'Male',
                    'dob' => '2005-01-' . str_pad($num, 2, '0', STR_PAD_LEFT),
                    'phone' => "09988776" . str_pad($num, 2, '0', STR_PAD_LEFT),
                    'email' => "student$num@example.com",
                    'id_card_number' => "ID-STUD-" . str_pad($num, 4, '0', STR_PAD_LEFT),
                    'student_type' => $type,
                    'status' => 'active',
                    'bacll_code' => "B" . str_pad($num, 5, '0', STR_PAD_LEFT),
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
                    'village' => 'Village ' . $num,
                ]
            );

            AcademicInfo::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'major_id' => $major->id,
                    'shift_id' => $student_shift_id,
                    'batch_year' => '2025',
                    'stage' => 'Year 1',
                    'study_days' => 'Mon-Fri',
                ]
            );

            ParentGuardian::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'father_name' => 'Father ' . $num,
                    'father_job' => 'Farmer',
                    'mother_name' => 'Mother ' . $num,
                    'mother_job' => 'Housewife',
                    'guardian_name' => 'Guardian ' . $num,
                    'guardian_job' => 'Merchant',
                    'guardian_phone' => '01122334' . str_pad($num, 2, '0', STR_PAD_LEFT),
                ]
            );

            Scholarship::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'nationality' => 'Khmer',
                    'ethnicity' => 'Khmer',
                    'emergency_name' => 'Emergency Contact ' . $num,
                    'emergency_relation' => 'Relative',
                    'emergency_phone' => '088112233' . str_pad($num, 2, '0', STR_PAD_LEFT),
                    'emergency_address' => 'Phnom Penh',
                    'grade' => 'A',
                    'exam_year' => '2023',
                ]
            );
        }

        // 9. Classes (5)
        $classes = [];
        for ($i = 1; $i <= 5; $i++) {
            $current_teacher_id = $teacherIds[($i - 1) % count($teacherIds)];
            $current_subject_id = $subjectIds[($i - 1) % count($subjectIds)];
            $current_shift_id   = $shiftIds[($i - 1) % count($shiftIds)];

            $cls = Classes::updateOrCreate(
                ['name' => "A$i"],
                [
                    'major_id' => $major->id,
                    'shift_id' => $current_shift_id,
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
                [
                    'major_id' => $major->id,
                    'shift_id' => $current_shift_id,
                    'year_level' => 1,
                    'semester' => 1,
                ]
            );

            ClassSchedule::updateOrCreate(
                ['class_id' => $cls->id, 'day_of_week' => 'Monday'],
                [
                    'subject_id' => $current_subject_id,
                    'teacher_id' => $current_teacher_id,
                    'shift_id' => $current_shift_id,
                    'academic_year' => '2025-2026',
                    'year_level' => 1,
                    'semester' => 1,
                    'room' => 'Room ' . $i,
                ]
            );

            // 10. Attendance Sessions
            $session = AttendanceSession::updateOrCreate(
                ['class_id' => $cls->id, 'subject_id' => $current_subject_id, 'session_date' => now()->toDateString(), 'session_number' => 1],
                [
                    'teacher_id' => $current_teacher_id,
                    'major_id' => $major->id,
                    'shift_id' => $current_shift_id,
                    'academic_year' => '2025-2026',
                    'year_level' => 1,
                    'semester' => 1,
                ]
            );

            // 11. Student Scores & Enrollments
            foreach ($students as $stu) {
                // Simplified: enroll every student in first class for testing, OR distribute them
                // Here we distribute them based on class index
                if (($i - 1) == ($students_index_for_class ?? -1)) {
                    // This logic is getting complex, let's stick to the simple distribution in next step
                }
            }
        }

        // 12. Enroll Students into Classes (Distribute evenly)
        foreach ($students as $index => $stu) {
            $cls = $classes[$index % count($classes)];
            $cls_schedule = ClassSchedule::where('class_id', $cls->id)->first();
            
            ClassStudent::updateOrCreate(
                ['class_id' => $cls->id, 'student_id' => $stu->id],
                ['joined_date' => now(), 'status' => 'Active']
            );

            StudentScore::updateOrCreate(
                ['student_id' => $stu->id, 'class_id' => $cls->id, 'subject_id' => $cls_schedule->subject_id],
                [
                    'academic_year' => '2025-2026',
                    'year_level' => 1,
                    'semester' => 1,
                    'class_score' => rand(7, 10),
                    'assignment_score' => rand(15, 20),
                    'midterm_score' => rand(20, 25),
                    'final_score' => rand(30, 40),
                ]
            );

            // Attendance Record for the session created in step 9
            $session = AttendanceSession::where('class_id', $cls->id)->first();
            if ($session) {
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
            ['type' => 'PENDING', 'name' => 'WAITING CANDIDATE A'],
            ['type' => 'PENDING', 'name' => 'WAITING CANDIDATE B'],
        ];

        foreach ($extraTypes as $index => $data) {
            $student = Students::updateOrCreate(
                ['full_name_en' => $data['name']],
                [
                    'full_name_kh' => "និស្សិតរង់ចាំ " . ($index + 1),
                    'gender' => $index % 2 == 0 ? 'Female' : 'Male',
                    'dob' => '2006-11-11',
                    'phone' => "0127788" . $index,
                    'email' => "waiting_list" . $index . "@example.com",
                    'id_card_number' => "ID-WAIT-" . str_pad($index, 4, '0', STR_PAD_LEFT),
                    'student_type' => $data['type'],
                    'status' => 'active',
                    'bacll_code' => "B" . str_pad($index + 50, 5, '0', STR_PAD_LEFT),
                    'grade' => 'B',
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
                    'shift_id' => $shiftIds[0],
                    'batch_year' => '2025',
                    'stage' => 'Year 1',
                    'study_days' => 'Mon-Fri',
                ]
            );
        }
    }
}
