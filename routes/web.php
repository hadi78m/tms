<?php

use App\Http\Controllers\Web\ApprovalController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\TaskAssignmentController;
use App\Http\Controllers\Web\TaskController;
use App\Http\Controllers\Web\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Task routes
    Route::resource('tasks', TaskController::class);

    // Task sub-routes
    Route::post('tasks/{task}/assign', [TaskAssignmentController::class, 'store'])->name('tasks.assign');
    Route::post('tasks/{task}/submit', [TaskController::class, 'submit'])->name('tasks.submit');
    Route::post('tasks/{task}/approvals', [ApprovalController::class, 'store'])->name('approvals.store');
    Route::post('tasks/{task}/documents', [DocumentController::class, 'store'])->name('documents.store');

    // User management (Admin only)
    Route::resource('users', UserController::class)->middleware('role:admin');
});
