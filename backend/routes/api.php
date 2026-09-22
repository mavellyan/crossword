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


// Auth controller routes
Route::post('/register', [\App\Http\Controllers\AuthController::class, 'register']);

Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout'])->middleware('auth:sanctum');


//User controller routes
Route::get('/profile', [\App\Http\Controllers\UserController::class, 'profile'])->middleware('auth:sanctum');

Route::get('/creators', [\App\Http\Controllers\UserController::class, 'index']);


// Crossword controller routes
Route::get('/listCrosswords', [\App\Http\Controllers\CrosswordController::class, 'listCrosswords']);

Route::get('/getCrossword', [\App\Http\Controllers\CrosswordController::class, 'getCrossword']);

Route::post('/createCrossword', [\App\Http\Controllers\CrosswordController::class, 'createCrossword'])->middleware('auth:sanctum');

Route::post('/validateWordForGuest', [\App\Http\Controllers\CrosswordController::class, 'validateWordForGuest']);

Route::post('/toggleVisibility', [\App\Http\Controllers\CrosswordController::class, 'toggleVisibility'])->middleware('auth:sanctum');

Route::get('/getCrosswordForEdit', [\App\Http\Controllers\CrosswordController::class, 'getCrosswordForEdit'])->middleware('auth:sanctum');

Route::put('/updateCrossword', [\App\Http\Controllers\CrosswordController::class, 'updateCrossword'])->middleware('auth:sanctum');

Route::delete('/deleteCrossword', [\App\Http\Controllers\CrosswordController::class, 'deleteCrossword'])->middleware('auth:sanctum');

// Attempt controller routes
Route::get('/getAttempt', [\App\Http\Controllers\AttemptController::class, 'getAttempt'])->middleware('auth:sanctum');

Route::post('/startAttempt', [\App\Http\Controllers\AttemptController::class, 'startAttempt'])->middleware('auth:sanctum');

Route::post('/stopAttempt', [\App\Http\Controllers\AttemptController::class, 'stopAttempt'])->middleware('auth:sanctum');

Route::post('/saveAndStopBeacon', [\App\Http\Controllers\AttemptController::class, 'saveAndStopBeacon'])->middleware('auth:sanctum');

Route::post('/abandonAttempt', [\App\Http\Controllers\AttemptController::class, 'abandonAttempt'])->middleware('auth:sanctum');

Route::get('/listBestAttempts', [\App\Http\Controllers\AttemptController::class, 'listBestAttempts']);

Route::post('/saveProgress', [\App\Http\Controllers\AttemptController::class, 'saveProgress'])->middleware('auth:sanctum');


// Clue controller routes
Route::get('/clues', [\App\Http\Controllers\ClueController::class, 'index']);

Route::post('/createClue', [\App\Http\Controllers\ClueController::class, 'create'])->middleware('auth:sanctum');


// Topic controller routes
Route::get('/topics', [\App\Http\Controllers\TopicController::class, 'index']);