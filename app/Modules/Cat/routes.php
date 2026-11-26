<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Cat\Controllers\AdminCatController;
use App\Modules\Cat\Controllers\CatController;
use App\Modules\Core\Middleware\RequireIdempotencyKey;

Route::prefix('cats')->group(function () {
    // Public cat reference photo stream (safe non-PII cat_photo only)
    Route::get('/{id}/photos/{mediaId}', [CatController::class, 'servePhoto'])->name('cats.photo');

    Route::middleware('auth:sanctum')->group(function () {
        // List with filters & pagination
        Route::get('/', [CatController::class, 'index']);

        // Search by printed collar unique code (Manual input)
        Route::get('/by-code/{unique_code}', [CatController::class, 'findByCode']);

        // Profile detail
        Route::get('/{id}', [CatController::class, 'show']);

        // Action Hub for cat profile
        Route::get('/{id}/actions', [CatController::class, 'actions']);

        // Record sighting (with Idempotency-Key)
        Route::post('/{id}/sightings', [CatController::class, 'recordSighting'])->middleware(RequireIdempotencyKey::class);
    });
});

// Admin / Data Curator CRUD endpoints — /api/v1/admin/cats/*
Route::prefix('admin/cats')->middleware('auth:sanctum')->group(function () {
    Route::middleware('can:cat.edit')->group(function () {
        Route::get('/', [AdminCatController::class, 'index']);
        Route::get('/{id}', [AdminCatController::class, 'show']);
        Route::post('/', [AdminCatController::class, 'store'])->middleware(RequireIdempotencyKey::class);
        Route::put('/{id}', [AdminCatController::class, 'update']);
        Route::patch('/{id}', [AdminCatController::class, 'update']);
        Route::post('/{id}/photos', [AdminCatController::class, 'uploadPhoto']);
        Route::get('/{id}/qr-download', [AdminCatController::class, 'downloadQr'])->name('admin.cats.qr-download');
        Route::delete('/{id}', [AdminCatController::class, 'destroy']);
        Route::post('/{id}/restore', [AdminCatController::class, 'restore']);
    });

    Route::middleware('can:cat.qr.regenerate')->group(function () {
        Route::post('/{id}/qr', [AdminCatController::class, 'regenerateQr']);
        Route::post('/{id}/qr/regenerate', [AdminCatController::class, 'regenerateQr']);
    });
});
