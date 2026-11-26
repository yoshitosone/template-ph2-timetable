<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TimetableEntryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\AdminEntryController;
use App\Http\Controllers\AdminAnnouncementController;
use App\Http\Controllers\AdminMaterialController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\AdminRosterController;
use App\Http\Controllers\AdminGradeController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/timetable', [TimetableController::class, 'index']);

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/entries', [TimetableEntryController::class, 'index']);
    Route::get('/entries/create', [TimetableEntryController::class, 'create']);
    Route::post('/entries', [TimetableEntryController::class, 'store'])->name('entries.store');
    Route::delete('/entries/{id}', [TimetableEntryController::class, 'destroy'])->name('entries.destroy');
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::post('/courses/{course}/reviews', [CourseController::class, 'storeReview']);
    Route::delete('/reviews/{review}', [\App\Http\Controllers\ReviewController::class, 'destroy']);
    Route::post('/courses/{course}/favorites', [CourseController::class, 'toggleFavorite']);
    Route::get('/materials/{material}/download', [MaterialController::class, 'download']);
    Route::get('/materials/{material}/preview', [MaterialController::class, 'preview']);
    Route::middleware('can:admin')->prefix('admin')->group(function () {
    Route::get('/entries', [AdminEntryController::class, 'index']);
    Route::post('/entries/{id}/approve', [AdminEntryController::class, 'approve']);
    Route::post('/entries/{id}/reject', [AdminEntryController::class, 'reject']);
    Route::get('/courses/{course}/announcements/create', [AdminAnnouncementController::class, 'create']);
    Route::post('/courses/{course}/announcements', [AdminAnnouncementController::class, 'store']);
    Route::post('/courses/{course}/materials', [AdminMaterialController::class, 'store']);
    Route::get('/courses/{course}/roster', [AdminRosterController::class, 'download']);
    Route::post('/courses/{course}/grades', [AdminGradeController::class, 'store']);
    Route::post('/materials/{material}/replace', [AdminMaterialController::class, 'replace']);
    Route::delete('/materials/{material}', [AdminMaterialController::class, 'destroy']);
});
});