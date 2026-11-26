<?php

use Illuminate\Support\Facades\Route;
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
