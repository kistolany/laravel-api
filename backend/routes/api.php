<?php

use App\Http\Controllers\ApiController\AcademicInfoController;
use App\Http\Controllers\ApiController\AuthController;
use App\Http\Controllers\ApiController\AttendanceSessionController;
use App\Http\Controllers\ApiController\CommuneController;
use App\Http\Controllers\ApiController\ClassController;
use App\Http\Controllers\ApiController\DistrictController;
use App\Http\Controllers\ApiController\FacultyController;
use App\Http\Controllers\ApiController\LookupController;
use App\Http\Controllers\ApiController\MajorController;
use App\Http\Controllers\ApiController\MajorSubjectController;
use App\Http\Controllers\ApiController\PermissionController;
use App\Http\Controllers\ApiController\ProvinceController;
use App\Http\Controllers\ApiController\RoleController;
use App\Http\Controllers\ApiController\ShiftController;
use App\Http\Controllers\ApiController\StudentController;
use App\Http\Controllers\ApiController\StudentCardController;
use App\Http\Controllers\ApiController\ScholarshipController;
use App\Http\Controllers\ApiController\StudentRegistrationController;
use App\Http\Controllers\ApiController\SubjectController;
use App\Http\Controllers\ApiController\TeacherAuthController;
use App\Http\Controllers\ApiController\TeacherModuleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
        'version' => 'v1',
    ]);
});

Route::get('/health', function () {
    $database = 'up';

    try {
        DB::connection()->getPdo();
    } catch (\Throwable $exception) {
        $database = 'down';
    }

    return response()->json([
        'status' => 'ok',
        'database' => $database,
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('v1')->group(function () {

    // --- 1. Lookup Routes (For Dropdowns/Forms) ---
    Route::prefix('lookups')->group(function () {
        Route::get('faculties', [LookupController::class, 'faculties']);
        Route::get('majors', [LookupController::class, 'majors']);
        Route::get('subjects', [LookupController::class, 'subjects']);
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
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('revoke', [AuthController::class, 'revoke']);
            Route::get('users', [AuthController::class, 'index'])->middleware('permission:view_user');
            Route::post('users', [AuthController::class, 'createUser'])->middleware('permission:create_user');
            Route::patch('users/{id}/status', [AuthController::class, 'updateStatus'])->middleware('permission:edit_user');
            Route::delete('users/{id}', [AuthController::class, 'destroy'])->middleware('permission:delete_user');
        });
    });

    Route::prefix('teacher-auth')->group(function () {
        Route::post('register', [TeacherAuthController::class, 'register']);
        Route::post('verify-otp', [TeacherAuthController::class, 'verifyOtp']);
        Route::post('resend-otp', [TeacherAuthController::class, 'resendOtp']);
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

        // Student specific routes (Admin + Assistant)
        Route::middleware('role:Admin|Assistant')->group(function () {
            Route::apiResource('students', StudentController::class);
            Route::patch('students/{id}/disable', [StudentController::class, 'setDisable']);
            Route::post('students/{id}/image', [StudentController::class, 'updateImage']);
            Route::get('students/{id}/classes', [StudentController::class, 'classes']);

            // Student Card specific routes
            Route::get('student-card/{student_id}', [StudentCardController::class, 'show']);
            Route::get('student-card/major/{major}', [StudentCardController::class, 'byMajor']);

            // Class specific routes
            Route::post('classes', [ClassController::class, 'store']);
            Route::post('classes/{id}/students', [ClassController::class, 'addStudent']);
            Route::post('classes/{id}/students/by-major', [ClassController::class, 'addStudentsByMajor']);
            Route::post('classes/{id}/subjects', [ClassController::class, 'assignSubject']);
        });

        // Attendance (Admin + Assistant + Teacher)
        Route::middleware('role:Admin|Assistant|Teacher')->group(function () {
            Route::get('classes', [ClassController::class, 'index']);
            Route::get('classes/{id}', [ClassController::class, 'show']);
            Route::get('classes/{id}/students', [ClassController::class, 'students']);
            Route::get('classes/{id}/subjects', [ClassController::class, 'subjects']);
            Route::get('attendance-sessions/major/{majorId}', [AttendanceSessionController::class, 'byMajor']);
            Route::get('attendance-sessions/major/{majorId}/subject/{subjectId}/report', [AttendanceSessionController::class, 'reportByMajorAndSubject']);
            Route::get('attendance-sessions/{id}', [AttendanceSessionController::class, 'show']);
            Route::post('attendance-sessions', [AttendanceSessionController::class, 'store']);
            Route::post('attendance-sessions/{id}/records', [AttendanceSessionController::class, 'record']);
        });

        // Teacher can only list majors from the secured majors endpoints.
        Route::middleware('role:Admin|Assistant|Teacher')->group(function () {
            Route::get('majors', [MajorController::class, 'index']);
        });

        // Academic and master data (Admin + Assistant)
        Route::middleware('role:Admin|Assistant')->group(function () {
            Route::apiResource('faculties', FacultyController::class);
            Route::apiResource('subjects', SubjectController::class);
            Route::apiResource('major-subjects', MajorSubjectController::class);
            Route::apiResource('majors', MajorController::class)->except(['index']);
            Route::apiResource('shifts', ShiftController::class);
            Route::apiResource('academic_info', AcademicInfoController::class);
            Route::apiResource('scholarships', ScholarshipController::class);
            Route::apiResource('student-registrations', StudentRegistrationController::class);

            // Location resources
            Route::apiResource('provinces', ProvinceController::class)->except(['index', 'show']);
            Route::apiResource('districts', DistrictController::class);
            Route::apiResource('communes', CommuneController::class);
        });

        // --- 3. Custom Filtering Routes (Admin + Assistant + Staff) ---
        Route::middleware('role:Admin|Assistant|Staff')->group(function () {
            Route::apiResource('provinces', ProvinceController::class)->only(['index', 'show']);
            Route::get('majors/faculty/{facultyId}', [MajorController::class, 'getByFaculty']);
            Route::get('academic_info/major/{majorId}', [AcademicInfoController::class, 'getByMajorId']);
            Route::get('academic_info/shift/{shiftId}', [AcademicInfoController::class, 'getByShiftId']);
        });

        Route::middleware('role:Admin|Assistant|Staff|Teacher')->group(function () {
            Route::get('major-subjects/major/{majorId}', [MajorSubjectController::class, 'getByMajor']);
        });
    });

    Route::middleware('auth.teacher')->prefix('teacher')->group(function () {
        Route::get('students', [TeacherModuleController::class, 'students']);
        Route::get('classes', [TeacherModuleController::class, 'classes']);
        Route::get('classes/{id}/students', [TeacherModuleController::class, 'classStudents']);
        Route::get('attendance/options', [TeacherModuleController::class, 'attendanceOptions']);
        Route::get('attendance/history', [TeacherModuleController::class, 'attendanceHistory']);
        Route::get('attendance/history/{id}', [TeacherModuleController::class, 'attendanceShow']);
        Route::post('attendance/sessions', [TeacherModuleController::class, 'attendanceStore']);
        Route::post('attendance/sessions/{id}/records', [TeacherModuleController::class, 'attendanceRecord']);
    });

});
