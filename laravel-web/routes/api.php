<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaseController;
use App\Http\Controllers\Api\CaseEventController;
use App\Http\Controllers\Api\CasePartyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\PlanCheckoutController;
use App\Http\Controllers\Api\TeamPermissionController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserIsEnabled;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/email/verify', [AuthController::class, 'verify'])->middleware('throttle:6,1');

Route::middleware(['auth:sanctum', EnsureEmailIsVerified::class, EnsureUserIsEnabled::class])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/people', [PersonController::class, 'index']);
    Route::post('/people', [PersonController::class, 'store']);
    Route::put('/people/{user}', [PersonController::class, 'update']);
    Route::delete('/people/{user}', [PersonController::class, 'destroy']);
    Route::get('/role-permissions', [TeamPermissionController::class, 'index']);
    Route::put('/role-permissions', [TeamPermissionController::class, 'update']);
    Route::post('/plans/{plan}/checkout', [PlanCheckoutController::class, 'checkout']);
    Route::post('/plans/verify', [PlanCheckoutController::class, 'verify']);
    Route::get('/casebook', [CaseController::class, 'index']);
    Route::post('/cases', [CaseController::class, 'store']);
    Route::get('/cases/{case}', [CaseController::class, 'show']);
    Route::put('/cases/{case}', [CaseController::class, 'update']);
    Route::delete('/cases/{case}', [CaseController::class, 'destroy']);
    Route::post('/cases/{case}/pin', [CaseController::class, 'togglePin']);
    Route::put('/cases/{case}/hearing', [CaseController::class, 'updateHearing']);
    Route::put('/cases/{case}/priority', [CaseController::class, 'updatePriority']);
    Route::put('/cases/{case}/status', [CaseController::class, 'updateStatus']);
    Route::put('/cases/{case}/mode', [CaseController::class, 'updateMode']);
    Route::put('/cases/{case}/tasks', [CaseController::class, 'updateTasks']);
    Route::put('/cases/{case}/parties', [CasePartyController::class, 'sync']);

    Route::post('/cases/{case}/events', [CaseEventController::class, 'store']);
    Route::put('/cases/{case}/events/{event}', [CaseEventController::class, 'update']);
    Route::delete('/cases/{case}/events/{event}', [CaseEventController::class, 'destroy']);
    Route::get('/events/{event}/file', [CaseEventController::class, 'file']);

    Route::post('/cases/{case}/documents', [DocumentController::class, 'store']);
    Route::delete('/cases/{case}/documents/{document}', [DocumentController::class, 'destroy']);
    Route::get('/documents/{document}/file', [DocumentController::class, 'file']);

    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates', [TemplateController::class, 'store']);
    Route::put('/templates/{template}', [TemplateController::class, 'update']);
    Route::get('/templates/{template}/export', [TemplateController::class, 'export']);
});
