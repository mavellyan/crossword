<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => 'crossword-backend',
    ]);
});

Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register']);

Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/getCrossword', [\App\Http\Controllers\CrosswordController::class, 'getCrossword']);

Route::get('/getAttempt', [\App\Http\Controllers\AttemptController::class, 'getAttempt'])->middleware('auth:sanctum');

Route::post('/createCrossword', [\App\Http\Controllers\CrosswordController::class, 'createCrossword'])->middleware('auth:sanctum');

Route::get('/listCrosswords', [\App\Http\Controllers\CrosswordController::class, 'listCrosswords']);

Route::get('/clues', [\App\Http\Controllers\ClueController::class, 'index']);

Route::post('/saveProgress', [\App\Http\Controllers\AttemptController::class, 'saveProgress'])->middleware('auth:sanctum');

Route::post('/createClue', [\App\Http\Controllers\ClueController::class, 'create'])->middleware('auth:sanctum');

Route::get('/topics', [\App\Http\Controllers\TopicController::class, 'index']);

Route::get('/creators', [\App\Http\Controllers\UserController::class, 'index']);

Route::post('/startAttempt', [\App\Http\Controllers\AttemptController::class, 'startAttempt'])->middleware('auth:sanctum');

Route::post('/stopAttempt', [\App\Http\Controllers\AttemptController::class, 'stopAttempt'])->middleware('auth:sanctum');

Route::post('/saveAndStopBeacon', [\App\Http\Controllers\AttemptController::class, 'saveAndStopBeacon'])->middleware('auth:sanctum');

Route::post('/abandonAttempt', [\App\Http\Controllers\AttemptController::class, 'abandonAttempt'])->middleware('auth:sanctum');

Route::get('/listBestAttempts', [\App\Http\Controllers\AttemptController::class, 'listBestAttempts']);