<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\CaseEventController;
use App\Http\Controllers\Api\CasePartyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/email/verify', [AuthController::class, 'verify'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', EnsureEmailIsVerified::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/casebook', [CaseController::class, 'index']);
    Route::post('/cases', [CaseController::class, 'store']);
    Route::get('/cases/{case}', [CaseController::class, 'show']);
    Route::put('/cases/{case}', [CaseController::class, 'update']);
    Route::delete('/cases/{case}', [CaseController::class, 'destroy']);
    Route::post('/cases/{case}/pin', [CaseController::class, 'togglePin']);
    Route::put('/cases/{case}/hearing', [CaseController::class, 'updateHearing']);
    Route::put('/cases/{case}/tasks', [CaseController::class, 'updateTasks']);
    Route::put('/cases/{case}/parties', [CasePartyController::class, 'sync']);

    Route::post('/cases/{case}/events', [CaseEventController::class, 'store']);
    Route::put('/cases/{case}/events/{event}', [CaseEventController::class, 'update']);
    Route::delete('/cases/{case}/events/{event}', [CaseEventController::class, 'destroy']);
    Route::get('/events/{event}/file', [CaseEventController::class, 'file']);

    Route::post('/cases/{case}/documents', [DocumentController::class, 'store']);
    Route::get('/documents/{document}/file', [DocumentController::class, 'file']);

    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::put('/templates/{template}', [TemplateController::class, 'update']);
    Route::get('/templates/{template}/export', [TemplateController::class, 'export']);
});
