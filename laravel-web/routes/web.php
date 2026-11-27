<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseModeController;
use App\Http\Controllers\CaseEventController;
use App\Http\Controllers\CasePartyController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PlanCheckoutController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\TeamPermissionController;
use App\Http\Controllers\TemplateController;
use App\Http\Middleware\EnsureCaseMode;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/openapi.yaml', function () {
    return response()->file(base_path('openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
});

Route::view('/docs', 'api-docs');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');

    Route::middleware([EnsureUserIsAdmin::class, EnsureUserIsEnabled::class])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
        Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');
        Route::get('/roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::put('/roles/{user}', [AdminRoleController::class, 'update'])->name('roles.update');
        Route::get('/permissions', [AdminPermissionController::class, 'index'])->name('permissions.index');
        Route::put('/permissions', [AdminPermissionController::class, 'update'])->name('permissions.update');
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
    });
});

Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->name('webhooks.razorpay');

Route::get('/email/verify/{id}/{hash}', EmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', EnsureEmailIsVerified::class, EnsureUserIsEnabled::class])->group(function () {
    Route::get('/mode', [CaseModeController::class, 'create'])->name('mode.create');
    Route::post('/mode', [CaseModeController::class, 'store'])->name('mode.store');

    Route::middleware(EnsureCaseMode::class)->group(function () {
    Route::get('/', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::get('/people', [PersonController::class, 'index'])->name('people.index');
    Route::post('/people', [PersonController::class, 'store'])->name('people.store');
    Route::put('/people/{user}', [PersonController::class, 'update'])->name('people.update');
    Route::delete('/people/{user}', [PersonController::class, 'destroy'])->name('people.destroy');
    Route::get('/roles-permissions', [TeamPermissionController::class, 'index'])->name('team.permissions.index');
    Route::put('/roles-permissions', [TeamPermissionController::class, 'update'])->name('team.permissions.update');
    Route::post('/plans/{plan}/checkout', [PlanCheckoutController::class, 'checkout'])->name('plans.checkout');
    Route::post('/plans/verify', [PlanCheckoutController::class, 'verify'])->name('plans.verify');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::put('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
    Route::delete('/cases/{case}', [CaseController::class, 'destroy'])->name('cases.destroy');
    Route::post('/cases/{case}/pin', [CaseController::class, 'togglePin'])->name('cases.pin');
    Route::post('/cases/{case}/hearing', [CaseController::class, 'updateHearing'])->name('cases.hearing');
    Route::post('/cases/{case}/priority', [CaseController::class, 'updatePriority'])->name('cases.priority');
    Route::post('/cases/{case}/status', [CaseController::class, 'updateStatus'])->name('cases.status');
    Route::post('/cases/{case}/mode', [CaseController::class, 'updateMode'])->name('cases.mode');
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
});
