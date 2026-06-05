<?php

use App\Http\Controllers\Api\V1\AuthController as ApiAuth;
use App\Http\Controllers\Api\V1\StudentController as ApiStudent;
use App\Http\Controllers\Api\V1\NoticeController as ApiNotice;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [ApiAuth::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [ApiAuth::class, 'logout']);
        Route::get('me', [ApiAuth::class, 'me']);
        Route::get('notices', [ApiNotice::class, 'index']);
        Route::get('notices/{notice}', [ApiNotice::class, 'show']);

        // Student endpoints
        Route::middleware('role:student')->prefix('student')->group(function () {
            Route::get('profile', [ApiStudent::class, 'profile']);
            Route::get('attendance', [ApiStudent::class, 'attendance']);
            Route::get('results', [ApiStudent::class, 'results']);
            Route::get('fees', [ApiStudent::class, 'fees']);
        });
    });
});
