<?php

use App\Http\Controllers\ApiController\AcademicInfo\AcademicInfoController;
use App\Http\Controllers\ApiController\Auth\AuthController;
use App\Http\Controllers\ApiController\Dashboard\DashboardController;
use App\Http\Controllers\ApiController\AuditLog\AuditLogController;
use App\Http\Controllers\ApiController\Attendance\AttendanceSessionController;
use App\Http\Controllers\ApiController\Address\CommuneController;
use App\Http\Controllers\ApiController\Class\ClassController;
use App\Http\Controllers\ApiController\Address\DistrictController;
use App\Http\Controllers\ApiController\Faculty\FacultyController;
use App\Http\Controllers\ApiController\Lookup\LookupController;
use App\Http\Controllers\ApiController\Major\MajorController;
use App\Http\Controllers\ApiController\Major\MajorSubjectController;
use App\Http\Controllers\ApiController\Permission\PermissionController;
use App\Http\Controllers\ApiController\Address\ProvinceController;
use App\Http\Controllers\ApiController\Role\RoleController;
use App\Http\Controllers\ApiController\Shift\ShiftController;
use App\Http\Controllers\ApiController\Student\StudentController;
use App\Http\Controllers\ApiController\Student\StudentCardController;
use App\Http\Controllers\ApiController\StudentPaymentController;
use App\Http\Controllers\ApiController\Scholarship\ScholarshipController;
use App\Http\Controllers\ApiController\Score\StudentScoreController;
use App\Http\Controllers\ApiController\Student\StudentRegistrationController;
use App\Http\Controllers\ApiController\Subject\SubjectController;
use App\Http\Controllers\ApiController\Teacher\TeacherAuthController;
use App\Http\Controllers\ApiController\Teacher\TeacherModuleController;
use App\Http\Controllers\ApiController\ClassSchedule\ClassScheduleController;
use App\Http\Controllers\ApiController\LeaveRequestController;
use App\Http\Controllers\ApiController\Chat\ChatController;
use App\Http\Controllers\ApiController\StaffAttendance\StaffAttendanceController;
use App\Http\Controllers\ApiController\TeacherAttendance\TeacherAttendanceController;
use App\Http\Controllers\ApiController\SubjectClassroom\SubjectClassroomController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    // --- 1. Lookup Routes (For Dropdowns/Forms) ---
    Route::prefix('lookups')->group(function () {
        Route::get('full', [LookupController::class, 'full']);
        Route::get('faculties', [LookupController::class, 'faculties']);
        Route::get('majors', [LookupController::class, 'majors']);
        Route::get('subjects', [LookupController::class, 'subjects']);
        Route::get('shifts', [LookupController::class, 'shifts']);
        Route::get('classes', [LookupController::class, 'classes']);
        Route::get('student-types', [LookupController::class, 'studentTypes']);
        Route::get('stages', [LookupController::class, 'stages']);
        Route::get('batch-years', [LookupController::class, 'batchYears']);
        Route::get('academic-years', [LookupController::class, 'academicYears']);
        Route::get('semesters', [LookupController::class, 'semesters']);
        Route::get('study-days', [LookupController::class, 'studyDays']);
        Route::get('score-filters', [LookupController::class, 'scoreFilters']);
        Route::get('attendance-filters', [LookupController::class, 'attendanceFilters']);
        Route::get('attendance-classes', [LookupController::class, 'attendanceClasses']);
        Route::get('attendance-subjects', [LookupController::class, 'attendanceSubjects']);
        Route::get('provinces', [LookupController::class, 'provinces']);
        Route::get('districts', [LookupController::class, 'districts']);
        Route::get('communes', [LookupController::class, 'communes']);
        Route::get('teachers', [LookupController::class, 'teachers']);
    });

    // Authentication & JWT
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('refresh', [AuthController::class, 'refresh']);

        Route::middleware('auth.jwt')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('profile', [AuthController::class, 'updateProfile']);
            Route::post('audit-logs', [AuditLogController::class, 'store']);
            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit_log.view');
            Route::delete('audit-logs', [AuditLogController::class, 'destroy'])->middleware('permission:audit_log.delete|audit_log.view');
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('revoke', [AuthController::class, 'revoke']);
            Route::get('users', [AuthController::class, 'index'])->middleware('permission:user.view');
            Route::post('users', [AuthController::class, 'createUser'])->middleware('permission:user.create');
            Route::post('users/{id}', [AuthController::class, 'updateUser'])->middleware('permission:user.update');
            Route::patch('users/{id}/status', [AuthController::class, 'updateStatus'])->middleware('permission:user.status.update');
            Route::delete('users/{id}', [AuthController::class, 'destroy'])->middleware('permission:user.delete');
        });
    });

    Route::prefix('teacher-auth')->group(function () {
        Route::get('list', [TeacherAuthController::class, 'index']);
        Route::delete('{id}', [TeacherAuthController::class, 'destroy']);
        Route::post('register', [TeacherAuthController::class, 'register']);
        Route::post('{id}', [TeacherAuthController::class, 'update']);
        Route::post('upload-image', [TeacherAuthController::class, 'uploadImage']);
        Route::post('login', [TeacherAuthController::class, 'login'])->middleware('throttle:login');
        Route::post('refresh', [TeacherAuthController::class, 'refresh']);

        Route::middleware('auth.teacher')->group(function () {
            Route::get('me', [TeacherAuthController::class, 'me']);
            Route::post('logout', [TeacherAuthController::class, 'logout']);
            Route::post('logout-all', [TeacherAuthController::class, 'logoutAll']);
            Route::post('revoke', [TeacherAuthController::class, 'revoke']);
        });
    });

    Route::middleware('auth.jwt')->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        // Teacher Attendance routes
        Route::get('teacher-attendances',         [TeacherAttendanceController::class, 'index'])->middleware('permission:teacher_attendance.view');
        Route::post('teacher-attendances/bulk',   [TeacherAttendanceController::class, 'bulk'])->middleware('permission:teacher_attendance.create');
        Route::get('teacher-attendances/history', [TeacherAttendanceController::class, 'history'])->middleware('permission:teacher_attendance.view');
        Route::get('teacher-attendances/report',  [TeacherAttendanceController::class, 'report'])->middleware('permission:teacher_attendance.view');

        // Staff Attendance routes
        Route::get('staff-attendances', [StaffAttendanceController::class, 'index'])->middleware('permission:staff_attendance.view|user.view');
        Route::post('staff-attendances/bulk', [StaffAttendanceController::class, 'bulk'])->middleware('permission:staff_attendance.create|staff_attendance.update|user.update');
        Route::get('staff-attendances/history', [StaffAttendanceController::class, 'history'])->middleware('permission:staff_attendance.view|user.view');
        Route::get('staff-attendances/report', [StaffAttendanceController::class, 'report'])->middleware('permission:staff_attendance.report|staff_attendance.view|user.view');

        // Role & Permission management
        Route::apiResource('roles', RoleController::class)->only(['index', 'show'])->middleware('permission:role.view');
        Route::apiResource('roles', RoleController::class)->only(['store'])->middleware('permission:role.create');
        Route::apiResource('roles', RoleController::class)->only(['update'])->middleware('permission:role.update');
        Route::apiResource('roles', RoleController::class)->only(['destroy'])->middleware('permission:role.delete');
        Route::apiResource('permissions', PermissionController::class)->only(['index', 'show'])->middleware('permission:permission.view|role.view');
        Route::apiResource('permissions', PermissionController::class)->only(['store'])->middleware('permission:permission.create|role.create');
        Route::apiResource('permissions', PermissionController::class)->only(['update'])->middleware('permission:permission.update|role.update');
        Route::apiResource('permissions', PermissionController::class)->only(['destroy'])->middleware('permission:permission.delete|role.delete');
        Route::post('roles/{id}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:role.update');

        // Student routes
        Route::get('students/pay-pass', [StudentController::class, 'payOrPass'])->middleware('permission:student.view');
        Route::get('students/final-exam-list', [StudentController::class, 'finalExamList'])->middleware('permission:student.view');
        Route::get('students/pass', [StudentController::class, 'passStudents'])->middleware('permission:student.view');
        Route::get('students/fail', [StudentController::class, 'failStudents'])->middleware('permission:student.view');
        Route::get('students/pending', [StudentController::class, 'pendingStudents'])->middleware('permission:student.view');
        Route::apiResource('students', StudentController::class)->only(['index', 'show'])->middleware('permission:student.view');
        Route::apiResource('students', StudentController::class)->only(['store'])->middleware('permission:student.create');
        Route::apiResource('students', StudentController::class)->only(['update'])->middleware('permission:student.update');
        Route::apiResource('students', StudentController::class)->only(['destroy'])->middleware('permission:student.delete');
        Route::patch('students/{id}/disable', [StudentController::class, 'setDisable'])->middleware('permission:student.disable');
        Route::patch('students/{id}/status', [StudentController::class, 'updateStatus'])->middleware('permission:student.status.update');
        Route::patch('students/{id}/student-type', [StudentController::class, 'updateStudentType'])->middleware('permission:student.update');
        Route::post('students/{id}/image', [StudentController::class, 'updateImage'])->middleware('permission:student.image.update');
        Route::get('students/{id}/classes', [StudentController::class, 'classes'])->middleware('permission:student.classes.view');

        // Student payment routes
        Route::get('student-payments/plans', [StudentPaymentController::class, 'plans'])->middleware('permission:student_payment.view|student.view');
        Route::get('student-payments', [StudentPaymentController::class, 'index'])->middleware('permission:student_payment.view|student.view');
        Route::get('student-payments/{id}', [StudentPaymentController::class, 'show'])->whereNumber('id')->middleware('permission:student_payment.view|student.view');
        Route::post('student-payments', [StudentPaymentController::class, 'store'])->middleware('permission:student_payment.create|student.create');
        Route::put('student-payments/{id}', [StudentPaymentController::class, 'update'])->whereNumber('id')->middleware('permission:student_payment.update|student.update');
        Route::delete('student-payments/{id}', [StudentPaymentController::class, 'destroy'])->whereNumber('id')->middleware('permission:student_payment.delete|student.delete');

        // Student Card routes
        Route::get('student-cards', [StudentCardController::class, 'index'])->middleware('permission:student.card.view');
        Route::get('student-card/{student_id}', [StudentCardController::class, 'show'])->whereNumber('student_id')->middleware('permission:student.card.view');
        Route::get('student-card/major/{major}', [StudentCardController::class, 'byMajor'])->middleware('permission:student.card.by_major.view');

        // Class routes
        Route::post('classes', [ClassController::class, 'store'])->middleware('permission:class.create');
        Route::get('classes', [ClassController::class, 'index'])->middleware('permission:class.view');
        Route::get('classes/{id}', [ClassController::class, 'show'])->middleware('permission:class.view');
        Route::put('classes/{id}', [ClassController::class, 'update'])->middleware('permission:class.create');
        Route::delete('classes/{id}', [ClassController::class, 'destroy'])->middleware('permission:class.delete');
        Route::get('classes/{id}/students', [ClassController::class, 'students'])->middleware('permission:class.students.view');
        Route::post('classes/{id}/programs', [ClassController::class, 'addProgram'])->middleware('permission:class.create');
        Route::delete('classes/{id}/programs/{programId}', [ClassController::class, 'removeProgram'])->middleware('permission:class.create');
        Route::get('classes/{id}/subjects', [ClassController::class, 'subjects'])->middleware('permission:class.subjects.view');
        Route::post('classes/{id}/students', [ClassController::class, 'addStudent'])->middleware('permission:class.students.add');
        Route::delete('classes/{id}/students/{studentId}', [ClassController::class, 'removeStudent'])->middleware('permission:class.students.add');
        Route::post('classes/{id}/students/by-major', [ClassController::class, 'addStudentsByMajor'])->middleware('permission:class.students.add_by_major');
        Route::post('classes/{id}/subjects', [ClassController::class, 'assignSubject'])->middleware('permission:class.subjects.assign');

        // Class Schedule routes
        Route::get('class-schedules', [ClassScheduleController::class, 'index'])->middleware('permission:class_schedule.view');
        Route::post('class-schedules', [ClassScheduleController::class, 'store'])->middleware('permission:class_schedule.create');
        Route::get('class-schedules/class/{classId}', [ClassScheduleController::class, 'byClass'])->middleware('permission:class_schedule.view');
        Route::get('class-schedules/{id}', [ClassScheduleController::class, 'show'])->middleware('permission:class_schedule.view');
        Route::put('class-schedules/{id}', [ClassScheduleController::class, 'update'])->middleware('permission:class_schedule.update');
        Route::delete('class-schedules/{id}', [ClassScheduleController::class, 'destroy'])->middleware('permission:class_schedule.delete');

        // Attendance routes
        Route::get('attendance-sessions', [AttendanceSessionController::class, 'index'])->middleware('permission:attendance.view');
        Route::get('attendance-sessions/matrix', [AttendanceSessionController::class, 'matrix'])->middleware('permission:attendance.view');
        Route::post('attendance-sessions/matrix', [AttendanceSessionController::class, 'saveMatrix'])->middleware('permission:attendance.create|attendance.record');
        Route::get('attendance-sessions/major/{majorId}', [AttendanceSessionController::class, 'byMajor'])->whereNumber('majorId')->middleware('permission:attendance.view');
        Route::get('attendance-sessions/major/{majorId}/subject/{subjectId}/report', [AttendanceSessionController::class, 'reportByMajorAndSubject'])->whereNumber('majorId')->whereNumber('subjectId')->middleware('permission:attendance.report.by_major_subject');
        Route::get('attendance-sessions/{id}', [AttendanceSessionController::class, 'show'])->whereNumber('id')->middleware('permission:attendance.view');
        Route::post('attendance-sessions', [AttendanceSessionController::class, 'store'])->middleware('permission:attendance.create');
        Route::post('attendance-sessions/{id}/records', [AttendanceSessionController::class, 'record'])->whereNumber('id')->middleware('permission:attendance.record');

        // Leave Requests
        Route::get('leave-requests', [LeaveRequestController::class, 'index'])->middleware('permission:leave_request.view');
        Route::post('leave-requests', [LeaveRequestController::class, 'store'])->middleware('permission:leave_request.create');
        Route::patch('leave-requests/{id}/status', [LeaveRequestController::class, 'updateStatus'])->middleware('permission:leave_request.approve');
        Route::delete('leave-requests/{id}', [LeaveRequestController::class, 'destroy'])->middleware('permission:leave_request.delete');

        // Chat / Messaging
        Route::prefix('chat')->group(function () {
            Route::get('users', [ChatController::class, 'users']);
            Route::get('conversations', [ChatController::class, 'conversations']);
            Route::post('conversations', [ChatController::class, 'findOrCreate']);
            Route::get('conversations/{id}/messages', [ChatController::class, 'messages'])->whereNumber('id');
            Route::post('conversations/{id}/messages', [ChatController::class, 'sendMessage'])->whereNumber('id');
            Route::patch('conversations/{id}/read', [ChatController::class, 'markRead'])->whereNumber('id');
            Route::get('unread-count', [ChatController::class, 'unreadCount']);
            Route::delete('conversations/{id}/clear', [ChatController::class, 'clearConversation'])->whereNumber('id');
            Route::delete('conversations/{id}', [ChatController::class, 'destroyConversation'])->whereNumber('id');
            Route::patch('conversations/{id}/mute', [ChatController::class, 'toggleMute'])->whereNumber('id');
            Route::delete('messages/{messageId}', [ChatController::class, 'deleteMessage'])->whereNumber('messageId');
        });

        // Student score routes
        Route::get('student-scores/grade-book', [StudentScoreController::class, 'gradeBook'])->middleware('permission:student.view');
        Route::get('student-scores/final-results', [StudentScoreController::class, 'finalResults'])->middleware('permission:student.view');
        Route::get('student-scores/reexam-results', [StudentScoreController::class, 'reexamResults'])->middleware('permission:student.view');
        Route::get('student-scores', [StudentScoreController::class, 'index'])->middleware('permission:student.view');
        Route::post('student-scores/bulk', [StudentScoreController::class, 'bulkUpsert'])->middleware('permission:student.update');

        // Major routes
        Route::get('majors', [MajorController::class, 'index'])->middleware('permission:major.view');
        Route::apiResource('majors', MajorController::class)->only(['show'])->middleware('permission:major.view');
        Route::apiResource('majors', MajorController::class)->only(['store'])->middleware('permission:major.create');
        Route::apiResource('majors', MajorController::class)->only(['update'])->middleware('permission:major.update');
        Route::apiResource('majors', MajorController::class)->only(['destroy'])->middleware('permission:major.delete');
        Route::get('majors/faculty/{facultyId}', [MajorController::class, 'getByFaculty'])->middleware('permission:major.by_faculty.view');

        // Faculty routes
        Route::get('faculties/tree', [FacultyController::class, 'tree'])->middleware('permission:faculty.view');
        Route::apiResource('faculties', FacultyController::class)->only(['index', 'show'])->middleware('permission:faculty.view');
        Route::apiResource('faculties', FacultyController::class)->only(['store'])->middleware('permission:faculty.create');
        Route::apiResource('faculties', FacultyController::class)->only(['update'])->middleware('permission:faculty.update');
        Route::apiResource('faculties', FacultyController::class)->only(['destroy'])->middleware('permission:faculty.delete');

        // Subject routes
        Route::apiResource('subjects', SubjectController::class)->only(['index', 'show'])->middleware('permission:subject.view');
        Route::apiResource('subjects', SubjectController::class)->only(['store'])->middleware('permission:subject.create');
        Route::apiResource('subjects', SubjectController::class)->only(['update'])->middleware('permission:subject.update');
        Route::apiResource('subjects', SubjectController::class)->only(['destroy'])->middleware('permission:subject.delete');

        // Major Subject routes
        Route::apiResource('major-subjects', MajorSubjectController::class)->only(['index', 'show'])->middleware('permission:major_subject.view');
        Route::apiResource('major-subjects', MajorSubjectController::class)->only(['store'])->middleware('permission:major_subject.create');
        Route::apiResource('major-subjects', MajorSubjectController::class)->only(['update'])->middleware('permission:major_subject.update');
        Route::apiResource('major-subjects', MajorSubjectController::class)->only(['destroy'])->middleware('permission:major_subject.delete');
        Route::get('major-subjects/major/{majorId}', [MajorSubjectController::class, 'getByMajor'])->middleware('permission:major_subject.by_major.view');

        // Academic Info routes
        Route::apiResource('academic_info', AcademicInfoController::class)->only(['index', 'show'])->middleware('permission:academic_info.view');
        Route::apiResource('academic_info', AcademicInfoController::class)->only(['store'])->middleware('permission:academic_info.create');
        Route::apiResource('academic_info', AcademicInfoController::class)->only(['update'])->middleware('permission:academic_info.update');
        Route::apiResource('academic_info', AcademicInfoController::class)->only(['destroy'])->middleware('permission:academic_info.delete');
        Route::get('academic_info/major/{majorId}', [AcademicInfoController::class, 'getByMajorId'])->middleware('permission:academic_info.by_major.view');
        Route::get('academic_info/shift/{shiftId}', [AcademicInfoController::class, 'getByShiftId'])->middleware('permission:academic_info.by_shift.view');

        // Shift routes
        Route::apiResource('shifts', ShiftController::class)->only(['index', 'show'])->middleware('permission:shift.view');
        Route::apiResource('shifts', ShiftController::class)->only(['store'])->middleware('permission:shift.create');
        Route::apiResource('shifts', ShiftController::class)->only(['update'])->middleware('permission:shift.update');
        Route::apiResource('shifts', ShiftController::class)->only(['destroy'])->middleware('permission:shift.delete');

        // Scholarship routes
        Route::apiResource('scholarships', ScholarshipController::class)->only(['index', 'show'])->middleware('permission:scholarship.view');
        Route::apiResource('scholarships', ScholarshipController::class)->only(['store'])->middleware('permission:scholarship.create');
        Route::apiResource('scholarships', ScholarshipController::class)->only(['update'])->middleware('permission:scholarship.update');
        Route::apiResource('scholarships', ScholarshipController::class)->only(['destroy'])->middleware('permission:scholarship.delete');

        // Student Registration routes
        Route::apiResource('student-registrations', StudentRegistrationController::class)->only(['index', 'show'])->middleware('permission:student_registration.view');
        Route::apiResource('student-registrations', StudentRegistrationController::class)->only(['store'])->middleware('permission:student_registration.create');
        Route::apiResource('student-registrations', StudentRegistrationController::class)->only(['update'])->middleware('permission:student_registration.update');
        Route::apiResource('student-registrations', StudentRegistrationController::class)->only(['destroy'])->middleware('permission:student_registration.delete');

        // Province routes
        Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show'])->middleware('permission:province.view');
        Route::apiResource('provinces', ProvinceController::class)->only(['store'])->middleware('permission:province.create');
        Route::apiResource('provinces', ProvinceController::class)->only(['update'])->middleware('permission:province.update');
        Route::apiResource('provinces', ProvinceController::class)->only(['destroy'])->middleware('permission:province.delete');

        // District routes
        Route::apiResource('districts', DistrictController::class)->only(['index', 'show'])->middleware('permission:district.view');
        Route::apiResource('districts', DistrictController::class)->only(['store'])->middleware('permission:district.create');
        Route::apiResource('districts', DistrictController::class)->only(['update'])->middleware('permission:district.update');
        Route::apiResource('districts', DistrictController::class)->only(['destroy'])->middleware('permission:district.delete');

        // Commune routes
        Route::apiResource('communes', CommuneController::class)->only(['index', 'show'])->middleware('permission:commune.view');
        Route::apiResource('communes', CommuneController::class)->only(['store'])->middleware('permission:commune.create');
        Route::apiResource('communes', CommuneController::class)->only(['update'])->middleware('permission:commune.update');
        Route::apiResource('communes', CommuneController::class)->only(['destroy'])->middleware('permission:commune.delete');


    });

    // Subject Classroom routes (Lessons, Homework, Submissions)
    // Accessible by both Teachers and Users (Admins/Students)
    Route::middleware('auth.unified')->prefix('subject-classroom')->group(function () {
        Route::get('options', [SubjectClassroomController::class, 'options']);
        Route::get('lessons', [SubjectClassroomController::class, 'lessons']);
        Route::post('lessons', [SubjectClassroomController::class, 'storeLesson']);
        Route::delete('lessons/{id}', [SubjectClassroomController::class, 'destroyLesson'])->whereNumber('id');
        Route::get('homework', [SubjectClassroomController::class, 'homework']);
        Route::post('homework', [SubjectClassroomController::class, 'storeHomework']);
        Route::put('homework/{id}', [SubjectClassroomController::class, 'updateHomework'])->whereNumber('id');
        Route::delete('homework/{id}', [SubjectClassroomController::class, 'destroyHomework'])->whereNumber('id');
        Route::get('homework/{id}/submissions', [SubjectClassroomController::class, 'submissions'])->whereNumber('id');
        Route::post('homework/{id}/submit', [SubjectClassroomController::class, 'submitHomework'])->whereNumber('id');
        Route::patch('submissions/{id}/grade', [SubjectClassroomController::class, 'gradeSubmission'])->whereNumber('id');
        Route::patch('submissions/{id}/review', [SubjectClassroomController::class, 'reviewSubmission'])->whereNumber('id');
    });

});
