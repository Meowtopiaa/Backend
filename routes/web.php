<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Cat\Controllers\AdminCatWebController;
use App\Modules\FeedingSpot\Controllers\AdminFeedingSpotController;

Route::get('/', function () {
    return response()->json([
        'message' => 'Welcome to Meowtopia API Backend',
        'api_docs' => '/api/v1',
    ]);
});

Route::get('/admin/login', [AdminFeedingSpotController::class, 'adminLogin'])->name('login');
Route::post('/admin/login', [AdminFeedingSpotController::class, 'adminAuthenticate'])->name('admin.login.submit');
Route::get('/admin', function () {
    $user = auth('web')->user();
    if ($user && ($user->can('spot.manage') || $user->can('feeding.review'))) {
        return redirect('/admin/feeding');
    }
    if ($user && ($user->can('cat.edit') || $user->can('cat.curate'))) {
        return redirect('/admin/cats');
    }

    return redirect('/admin/login');
})->middleware('auth')->name('admin.home');

Route::middleware('auth')->prefix('admin/cats')->group(function () {
    Route::middleware('can:cat.edit')->group(function () {
        Route::get('/', [AdminCatWebController::class, 'index'])->name('admin.cats.index');
        Route::post('/', [AdminCatWebController::class, 'store'])->name('admin.cats.store');
        Route::post('/{id}/update', [AdminCatWebController::class, 'update'])->name('admin.cats.update');
        Route::post('/{id}/delete', [AdminCatWebController::class, 'destroy'])->name('admin.cats.destroy');
        Route::post('/{id}/restore', [AdminCatWebController::class, 'restore'])->name('admin.cats.restore');
        Route::get('/qr-sheet', [AdminCatWebController::class, 'printAllQrSheets'])->name('admin.cats.qr-sheet.all');
        Route::get('/{id}/qr-sheet', [AdminCatWebController::class, 'printQrSheet'])->name('admin.cats.qr-sheet');
        Route::get('/{id}/qr-download', [AdminCatWebController::class, 'downloadQr'])->name('admin.cats.qr-download');
    });

    Route::middleware('can:cat.qr.regenerate')->group(function () {
        Route::post('/{id}/qr', [AdminCatWebController::class, 'regenerateQr'])->name('admin.cats.qr.regenerate');
    });
});

Route::middleware('auth')->prefix('admin/feeding')->group(function () {
    Route::post('/logout', [AdminFeedingSpotController::class, 'adminLogout'])->name('admin.logout');
    Route::get('/', [AdminFeedingSpotController::class, 'adminPreviewPanel']);

    Route::middleware('can:spot.manage')->group(function () {
        Route::post('/spots', [AdminFeedingSpotController::class, 'adminPreviewStoreSpot']);
        Route::post('/spots/{id}/update', [AdminFeedingSpotController::class, 'adminPreviewUpdateSpot']);
        Route::post('/spots/{id}/qr', [AdminFeedingSpotController::class, 'adminPreviewRegenerateQr']);
        Route::post('/spots/{id}/cats', [AdminFeedingSpotController::class, 'adminPreviewAttachCat']);
        Route::post('/spots/{id}/cats/{catId}/detach', [AdminFeedingSpotController::class, 'adminPreviewDetachCat']);
        Route::post('/settings', [AdminFeedingSpotController::class, 'adminPreviewUpdateSettings']);
        Route::get('/qr-sheet', [AdminFeedingSpotController::class, 'adminPreviewAllQrSheets']);
        Route::get('/spots/{id}/qr-sheet', [AdminFeedingSpotController::class, 'adminPreviewQrSheet']);
        Route::get('/spots/{id}/qr-download', [AdminFeedingSpotController::class, 'downloadSpotQr']);
    });

    Route::middleware('can:feeding.review')->group(function () {
        Route::get('/reviews/{id}', [AdminFeedingSpotController::class, 'adminPreviewReview']);
        Route::post('/activities/{id}/review', [AdminFeedingSpotController::class, 'adminPreviewSubmitReview']);
    });
});
