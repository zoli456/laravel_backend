<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormController;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/forms', [FormController::class, 'store']);
    Route::put('/forms/{id}', [FormController::class, 'update']);
    Route::delete('/forms/{id}', [FormController::class, 'destroy']);
    Route::get('/users', [AuthController::class, 'listUsers']);
    Route::put('/user/{userId}/update-user', [AuthController::class, 'updateUserDetails']);
    Route::get('/user/{userId}', [AuthController::class, 'getUserById']);
    Route::delete('/user/{userId}', [AuthController::class, 'deleteUser']);
    Route::get('/list-roles', [AuthController::class, 'listRoles']);
    Route::put('/user/{userId}/add-role', [AuthController::class, 'assignRole']);
    Route::delete('/user/{userId}/remove-role', [AuthController::class, 'removeRole']);
    Route::get('/forms/{formId}/answers', [FormController::class, 'getFormAnswers']);
    Route::delete('/answers/{submissionId}', [FormController::class, 'deleteAnswer']);
});
Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'userProfile']);
    Route::put('/user', [AuthController::class, 'updateCredentials']);
    Route::post('/forms/{formId}/submit', [FormController::class, 'submitForm']);
    Route::get('/forms', [FormController::class, 'index']);
    Route::get('/forms/{id}', [FormController::class, 'show']);
});


