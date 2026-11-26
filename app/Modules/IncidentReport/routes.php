<?php

use App\Modules\Core\Middleware\RequireIdempotencyKey;
use App\Modules\IncidentReport\Controllers\AdminCaretakerController;
use App\Modules\IncidentReport\Controllers\AdminIncidentController;
use App\Modules\IncidentReport\Controllers\AdminVetController;
use App\Modules\IncidentReport\Controllers\CaretakerTaskController;
use App\Modules\IncidentReport\Controllers\IncidentReportController;
use App\Modules\IncidentReport\Middleware\EnsureEmailVerified;
use App\Modules\IncidentReport\Middleware\RequireIncidentPermission;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Emergency Incident Reporting (Optimind) — PRD 8.7
|--------------------------------------------------------------------------
| Semua endpoint butuh login dan email terverifikasi (FIX-02, PRD 8.1).
| Endpoint † memakai Idempotency-Key. EnsureEmailVerified dan pemeriksaan izin
| berjalan sebelum Idempotency-Key agar respons 403 tidak tersimpan sebagai cache.
*/

$writeLimit = 'throttle:60,1'; // PRD 8.1: endpoint tulis 60 per menit per pengguna.
$admin = RequireIncidentPermission::class . ':incident.view,incident.dispatch';
$dispatch = RequireIncidentPermission::class . ':incident.dispatch';
$close = RequireIncidentPermission::class . ':incident.close';

Route::middleware(['auth:sanctum', EnsureEmailVerified::class])->group(function () use ($writeLimit, $admin, $dispatch, $close) {
    // Pelapor
    Route::prefix('incidents')->group(function () use ($writeLimit) {
        Route::post('/', [IncidentReportController::class, 'store'])
            ->middleware([$writeLimit, RequireIdempotencyKey::class]);
        Route::get('/mine', [IncidentReportController::class, 'mine']);
        Route::get('/{id}', [IncidentReportController::class, 'show']);
        Route::post('/{id}/status', [IncidentReportController::class, 'updateStatus'])
            ->middleware([$writeLimit, RequireIdempotencyKey::class]);
    });

    Route::get('/vets', [IncidentReportController::class, 'vets']);

    // Relawan
    Route::prefix('caretaker/tasks')->group(function () use ($writeLimit) {
        Route::get('/', [CaretakerTaskController::class, 'index']);
        Route::post('/{id}/accept', [CaretakerTaskController::class, 'accept'])->middleware($writeLimit);
        Route::post('/{id}/decline', [CaretakerTaskController::class, 'decline'])->middleware($writeLimit);
    });

    // Admin insiden
    Route::prefix('admin/incidents')->middleware($admin)->group(function () use ($writeLimit, $dispatch, $close) {
        Route::get('/', [AdminIncidentController::class, 'index']);
        Route::get('/hotspots', [AdminIncidentController::class, 'hotspots']);
        Route::get('/{id}', [AdminIncidentController::class, 'show']);
        Route::get('/{id}/candidates', [AdminIncidentController::class, 'candidates']);

        Route::middleware([$dispatch, $writeLimit])->group(function () use ($close) {
            Route::patch('/{id}', [AdminIncidentController::class, 'update']);
            Route::post('/{id}/verify', [AdminIncidentController::class, 'verify']);
            Route::post('/{id}/assign', [AdminIncidentController::class, 'assign']);
            Route::post('/{id}/close', [AdminIncidentController::class, 'close'])->middleware($close);
        });
    });

    Route::prefix('admin')->middleware([$dispatch])->group(function () use ($writeLimit) {
        Route::get('/vets', [AdminVetController::class, 'index']);
        Route::get('/vets/{id}', [AdminVetController::class, 'show']);
        Route::post('/vets', [AdminVetController::class, 'store'])->middleware($writeLimit);
        Route::match(['put', 'patch'], '/vets/{id}', [AdminVetController::class, 'update'])->middleware($writeLimit);
        Route::delete('/vets/{id}', [AdminVetController::class, 'destroy'])->middleware($writeLimit);

        Route::get('/caretakers', [AdminCaretakerController::class, 'index']);
        Route::get('/caretakers/{userId}', [AdminCaretakerController::class, 'show']);
        Route::post('/caretakers', [AdminCaretakerController::class, 'store'])->middleware($writeLimit);
        Route::match(['put', 'patch'], '/caretakers/{userId}', [AdminCaretakerController::class, 'update'])->middleware($writeLimit);
        Route::delete('/caretakers/{userId}', [AdminCaretakerController::class, 'destroy'])->middleware($writeLimit);
    });
});

// Console command (pola yang sama dengan modul FeedingSpot).
if (app()->runningInConsole()) {
    \Illuminate\Console\Application::starting(function ($artisan) {
        $artisan->resolve(\App\Modules\IncidentReport\Commands\ExpireIncidentOffersCommand::class);
    });
}
