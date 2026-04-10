<?php

use App\Http\Controllers\ApiController\AcademicInfo\AcademicInfoController;
use App\Http\Controllers\ApiController\Auth\AuthController;
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
use App\Http\Controllers\ApiController\Scholarship\ScholarshipController;
use App\Http\Controllers\ApiController\Student\StudentRegistrationController;
use App\Http\Controllers\ApiController\Subject\SubjectController;
use App\Http\Controllers\ApiController\Teacher\TeacherAuthController;
use App\Http\Controllers\ApiController\Teacher\TeacherModuleController;
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
        Route::get('provinces', [LookupController::class, 'provinces']);
        Route::get('districts', [LookupController::class, 'districts']);
        Route::get('communes', [LookupController::class, 'communes']);
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
            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:Admin');
            Route::delete('audit-logs', [AuditLogController::class, 'destroy'])->middleware('role:Admin');
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
        Route::post('register', [TeacherAuthController::class, 'register']);
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
        // Role & Permission management (Admin only)
        Route::middleware('role:Admin')->group(function () {
            Route::apiResource('roles', RoleController::class);
            Route::apiResource('permissions', PermissionController::class);
            Route::post('roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
        });

        // Student routes
        Route::get('students/pay-pass', [StudentController::class, 'payOrPass'])->middleware('permission:student.view');
        Route::apiResource('students', StudentController::class)->middleware([
            'index' => 'permission:student.view',
            'show' => 'permission:student.view',
            'store' => 'permission:student.create',
            'update' => 'permission:student.update',
            'destroy' => 'permission:student.delete',
        ]);
        Route::patch('students/{id}/disable', [StudentController::class, 'setDisable'])->middleware('permission:student.disable');
        Route::patch('students/{id}/student-type', [StudentController::class, 'updateStudentType'])->middleware('permission:student.update');
        Route::post('students/{id}/image', [StudentController::class, 'updateImage'])->middleware('permission:student.image.update');
        Route::get('students/{id}/classes', [StudentController::class, 'classes'])->middleware('permission:student.classes.view');

        // Student Card routes
        Route::get('student-card/{student_id}', [StudentCardController::class, 'show'])->middleware('permission:student.card.view');
        Route::get('student-card/major/{major}', [StudentCardController::class, 'byMajor'])->middleware('permission:student.card.by_major.view');

        // Class routes
        Route::post('classes', [ClassController::class, 'store'])->middleware('permission:class.create');
        Route::get('classes', [ClassController::class, 'index'])->middleware('permission:class.view');
        Route::get('classes/{id}', [ClassController::class, 'show'])->middleware('permission:class.view');
        Route::get('classes/{id}/students', [ClassController::class, 'students'])->middleware('permission:class.students.view');
        Route::get('classes/{id}/subjects', [ClassController::class, 'subjects'])->middleware('permission:class.subjects.view');
        Route::post('classes/{id}/students', [ClassController::class, 'addStudent'])->middleware('permission:class.students.add');
        Route::post('classes/{id}/students/by-major', [ClassController::class, 'addStudentsByMajor'])->middleware('permission:class.students.add_by_major');
        Route::post('classes/{id}/subjects', [ClassController::class, 'assignSubject'])->middleware('permission:class.subjects.assign');

        // Attendance routes
        Route::get('attendance-sessions/major/{majorId}', [AttendanceSessionController::class, 'byMajor'])->middleware('permission:attendance.view');
        Route::get('attendance-sessions/major/{majorId}/subject/{subjectId}/report', [AttendanceSessionController::class, 'reportByMajorAndSubject'])->middleware('permission:attendance.report.by_major_subject');
        Route::get('attendance-sessions/{id}', [AttendanceSessionController::class, 'show'])->middleware('permission:attendance.view');
        Route::post('attendance-sessions', [AttendanceSessionController::class, 'store'])->middleware('permission:attendance.create');
        Route::post('attendance-sessions/{id}/records', [AttendanceSessionController::class, 'record'])->middleware('permission:attendance.record');

        // Major routes
        Route::get('majors', [MajorController::class, 'index'])->middleware('permission:major.view');
        Route::apiResource('majors', MajorController::class)->except(['index'])->middleware([
            'show' => 'permission:major.view',
            'store' => 'permission:major.create',
            'update' => 'permission:major.update',
            'destroy' => 'permission:major.delete',
        ]);
        Route::get('majors/faculty/{facultyId}', [MajorController::class, 'getByFaculty'])->middleware('permission:major.by_faculty.view');

        // Faculty routes
        Route::apiResource('faculties', FacultyController::class)->middleware([
            'index' => 'permission:faculty.view',
            'show' => 'permission:faculty.view',
            'store' => 'permission:faculty.create',
            'update' => 'permission:faculty.update',
            'destroy' => 'permission:faculty.delete',
        ]);

        // Subject routes
        Route::apiResource('subjects', SubjectController::class)->middleware([
            'index' => 'permission:subject.view',
            'show' => 'permission:subject.view',
            'store' => 'permission:subject.create',
            'update' => 'permission:subject.update',
            'destroy' => 'permission:subject.delete',
        ]);

        // Major Subject routes
        Route::apiResource('major-subjects', MajorSubjectController::class)->middleware([
            'index' => 'permission:major_subject.view',
            'show' => 'permission:major_subject.view',
            'store' => 'permission:major_subject.create',
            'update' => 'permission:major_subject.update',
            'destroy' => 'permission:major_subject.delete',
        ]);
        Route::get('major-subjects/major/{majorId}', [MajorSubjectController::class, 'getByMajor'])->middleware('permission:major_subject.by_major.view');

        // Academic Info routes
        Route::apiResource('academic_info', AcademicInfoController::class)->middleware([
            'index' => 'permission:academic_info.view',
            'show' => 'permission:academic_info.view',
            'store' => 'permission:academic_info.create',
            'update' => 'permission:academic_info.update',
            'destroy' => 'permission:academic_info.delete',
        ]);
        Route::get('academic_info/major/{majorId}', [AcademicInfoController::class, 'getByMajorId'])->middleware('permission:academic_info.by_major.view');
        Route::get('academic_info/shift/{shiftId}', [AcademicInfoController::class, 'getByShiftId'])->middleware('permission:academic_info.by_shift.view');

        // Shift routes
        Route::apiResource('shifts', ShiftController::class)->middleware([
            'index' => 'permission:shift.view',
            'show' => 'permission:shift.view',
            'store' => 'permission:shift.create',
            'update' => 'permission:shift.update',
            'destroy' => 'permission:shift.delete',
        ]);

        // Scholarship routes
        Route::apiResource('scholarships', ScholarshipController::class)->middleware([
            'index' => 'permission:scholarship.view',
            'show' => 'permission:scholarship.view',
            'store' => 'permission:scholarship.create',
            'update' => 'permission:scholarship.update',
            'destroy' => 'permission:scholarship.delete',
        ]);

        // Student Registration routes
        Route::apiResource('student-registrations', StudentRegistrationController::class)->middleware([
            'index' => 'permission:student_registration.view',
            'show' => 'permission:student_registration.view',
            'store' => 'permission:student_registration.create',
            'update' => 'permission:student_registration.update',
            'destroy' => 'permission:student_registration.delete',
        ]);

        // Province routes
        Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show'])->middleware([
            'index' => 'permission:province.view',
            'show' => 'permission:province.view',
        ]);
        Route::apiResource('provinces', ProvinceController::class)->except(['index', 'show'])->middleware([
            'store' => 'permission:province.create',
            'update' => 'permission:province.update',
            'destroy' => 'permission:province.delete',
        ]);

        // District routes
        Route::apiResource('districts', DistrictController::class)->middleware([
            'index' => 'permission:district.view',
            'show' => 'permission:district.view',
            'store' => 'permission:district.create',
            'update' => 'permission:district.update',
            'destroy' => 'permission:district.delete',
        ]);

        // Commune routes
        Route::apiResource('communes', CommuneController::class)->middleware([
            'index' => 'permission:commune.view',
            'show' => 'permission:commune.view',
            'store' => 'permission:commune.create',
            'update' => 'permission:commune.update',
            'destroy' => 'permission:commune.delete',
        ]);
    });

    // Route::middleware('auth.teacher')->prefix('teacher')->group(function () {
    //     Route::get('students', [TeacherModuleController::class, 'students']);
    //     Route::get('classes', [TeacherModuleController::class, 'classes']);
    //     Route::get('classes/{id}/students', [TeacherModuleController::class, 'classStudents']);
    //     Route::get('attendance/options', [TeacherModuleController::class, 'attendanceOptions']);
    //     Route::get('attendance/history', [TeacherModuleController::class, 'attendanceHistory']);
    //     Route::get('attendance/history/{id}', [TeacherModuleController::class, 'attendanceShow']);
    //     Route::post('attendance/sessions', [TeacherModuleController::class, 'attendanceStore']);
    //     Route::post('attendance/sessions/{id}/records', [TeacherModuleController::class, 'attendanceRecord']);
    // });

});
