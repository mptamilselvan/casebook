<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseEventController;
use App\Http\Controllers\CasePartyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\TemplateController;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', EnsureEmailIsVerified::class])->group(function () {
    Route::get('/', [CaseController::class, 'index'])->name('cases.index');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::put('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
    Route::delete('/cases/{case}', [CaseController::class, 'destroy'])->name('cases.destroy');
    Route::post('/cases/{case}/pin', [CaseController::class, 'togglePin'])->name('cases.pin');
    Route::post('/cases/{case}/hearing', [CaseController::class, 'updateHearing'])->name('cases.hearing');
    Route::post('/cases/{case}/tasks', [CaseController::class, 'updateTasks'])->name('cases.tasks');
    Route::post('/cases/{case}/parties', [CasePartyController::class, 'sync'])->name('parties.sync');

    Route::post('/cases/{case}/events', [CaseEventController::class, 'store'])->name('events.store');
    Route::put('/cases/{case}/events/{event}', [CaseEventController::class, 'update'])->name('events.update');
    Route::delete('/cases/{case}/events/{event}', [CaseEventController::class, 'destroy'])->name('events.destroy');
    Route::get('/events/{event}/file', [CaseEventController::class, 'file'])->name('events.file');

    Route::post('/cases/{case}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('/documents/{document}/file', [DocumentController::class, 'file'])->name('documents.file');

    Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::get('/templates/{template}/export', [TemplateController::class, 'export'])->name('templates.export');
});
