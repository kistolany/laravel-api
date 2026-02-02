<?php

use App\Http\Controllers\Api\V1\FacultyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    // This one line handles all 5-7 actions automatically
    Route::apiResource('faculties', FacultyController::class);
});


