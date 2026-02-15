<?php

use App\Http\Controllers\Api\V1\FacultyController;
use App\Http\Controllers\Api\V1\MajorController;
use App\Http\Controllers\Api\V1\MajorSubjectController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\AcademicInfoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // This one line handles all 5-7 actions automatically
    Route::apiResource('faculties', FacultyController::class);
    Route::apiResource('subjects', SubjectController::class);
    Route::apiResource('major-subjects',MajorSubjectController::class);
    Route::apiResource('majors', MajorController::class);
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('academic_info', AcademicInfoController::class);
    
    // 1. Custom Majors Route (Specific)
    Route::get('majors/faculty/{facultyId}', [MajorController::class, 'getByFaculty']);
    Route::get('major-subjects/major/{majorId}', [MajorSubjectController::class, 'getByMajor']);

    //Route::get('academic_info/student/{studentId}', [AcademicInfoController::class, 'getByStudentId']);
    Route::get('academic_info/major/{majorId}', [AcademicInfoController::class, 'getByMajorId']);
    Route::get('academic_info/shift/{shiftId}', [AcademicInfoController::class, 'getByShiftId']);
    
});
