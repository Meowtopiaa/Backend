<?php

use App\Modules\CatHunter\Controllers\CatHunterController;
use App\Modules\Core\Middleware\RequireIdempotencyKey;
use Illuminate\Support\Facades\Route;

// /api/v1/cat-hunter/* (prefix /api/v1 ditambahkan oleh ModuleServiceProvider)
Route::prefix('cat-hunter')->middleware('auth:sanctum')->group(function () {
    Route::get('cats', [CatHunterController::class, 'cats']);

    // Read-only check (QR + cooldown), tidak memerlukan Idempotency-Key.
    Route::post('cats/{cat}/scan-check', [CatHunterController::class, 'scanCheck']);

    // Endpoint mutasi: wajib Idempotency-Key.
    Route::post('cats/{cat}/encounters', [CatHunterController::class, 'completeEncounter'])
        ->middleware(RequireIdempotencyKey::class);
    Route::patch('cats/{cat}/name', [CatHunterController::class, 'rename'])
        ->middleware(RequireIdempotencyKey::class);

    Route::get('zones', [CatHunterController::class, 'zones']);
    Route::get('profile', [CatHunterController::class, 'profile']);
    Route::patch('profile/avatar', [CatHunterController::class, 'setAvatar'])
        ->middleware(RequireIdempotencyKey::class);
});
