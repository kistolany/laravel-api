<?php

use App\Http\Controllers\ApiController\AcademicInfoController;
use App\Http\Controllers\ApiController\CommuneController;
use App\Http\Controllers\ApiController\ClassController;
use App\Http\Controllers\ApiController\DistrictController;
use App\Http\Controllers\ApiController\FacultyController;
use App\Http\Controllers\ApiController\LookupController;
use App\Http\Controllers\ApiController\MajorController;
use App\Http\Controllers\ApiController\MajorSubjectController;
use App\Http\Controllers\ApiController\ProvinceController;
use App\Http\Controllers\ApiController\ShiftController; 
use App\Http\Controllers\ApiController\StudentController;
use App\Http\Controllers\ApiController\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

    // --- 1. Lookup Routes (For Dropdowns/Forms) ---
    Route::prefix('lookups')->group(function () {
        Route::get('faculties', [LookupController::class, 'faculties']);
        Route::get('majors', [LookupController::class, 'majors']);
        Route::get('provinces', [LookupController::class, 'provinces']);
        Route::get('districts', [LookupController::class, 'districts']);
        Route::get('communes', [LookupController::class, 'communes']);
    });

    // --- 2. Standard API Resources ---
    Route::apiResource('students', StudentController::class);
    Route::patch('students/{id}/inactive', [StudentController::class, 'setInactive']);
    Route::post('students/{id}/image', [StudentController::class, 'updateImage']);
    Route::get('students/{id}/classes', [StudentController::class, 'classes']);
    Route::get('classes', [ClassController::class, 'index']);
    Route::post('classes', [ClassController::class, 'store']);
    Route::get('classes/{id}', [ClassController::class, 'show']);
    Route::get('classes/{id}/students', [ClassController::class, 'students']);
    Route::post('classes/{id}/students', [ClassController::class, 'addStudent']);
    Route::post('classes/{id}/students/by-major', [ClassController::class, 'addStudentsByMajor']);
    Route::apiResource('faculties', FacultyController::class);
    Route::apiResource('subjects', SubjectController::class);
    Route::apiResource('major-subjects', MajorSubjectController::class);
    Route::apiResource('majors', MajorController::class);
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('academic_info', AcademicInfoController::class);
    Route::apiResource('provinces', ProvinceController::class);
    Route::apiResource('districts', DistrictController::class);
    Route::apiResource('communes', CommuneController::class);

    // --- 3. Custom Filtering Routes ---
    Route::get('majors/faculty/{facultyId}', [MajorController::class, 'getByFaculty']);
    Route::get('major-subjects/major/{majorId}', [MajorSubjectController::class, 'getByMajor']);
    Route::get('academic_info/major/{majorId}', [AcademicInfoController::class, 'getByMajorId']);
    Route::get('academic_info/shift/{shiftId}', [AcademicInfoController::class, 'getByShiftId']);
});
