<?php

namespace App\Modules\CatHunter\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cat\Models\Cat;
use App\Modules\CatHunter\Exceptions\CatHunterException;
use App\Modules\CatHunter\Requests\CompleteEncounterRequest;
use App\Modules\CatHunter\Requests\RenameCatRequest;
use App\Modules\CatHunter\Requests\SetFavoriteAvatarRequest;
use App\Modules\CatHunter\Requests\StartEncounterRequest;
use App\Modules\CatHunter\Services\CatHunterService;
use App\Modules\Core\Services\ScanResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CatHunterController extends Controller
{
    public function __construct(private readonly CatHunterService $service)
    {
    }

    /**
     * GET /api/v1/cat-hunter/cats
     */
    public function cats(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getUserCatalog($request->user()),
            'Katalog kucing berhasil diambil.'
        );
    }

    /**
     * POST /api/v1/cat-hunter/cats/{cat}/scan-check
     * Cek QR/kode kalung (opsional) dan status cooldown harian sebelum sesi mengelus dimulai.
     * Hanya membaca data, jadi tidak memakai Idempotency-Key.
     */
    public function scanCheck(StartEncounterRequest $request, ScanResolver $resolver, string $cat): JsonResponse
    {
        $catModel = $this->findCat($cat);
        if (!$catModel) {
            return $this->catNotFound();
        }

        $code = $request->validated('code');
        if ($code) {
            try {
                $resolved = $resolver->resolve($code);
            } catch (HttpException $e) {
                return ApiResponse::error($e->getMessage(), $e->getStatusCode(), 'QR_INVALID');
            }

            if (($resolved['type'] ?? null) !== 'cat' || $resolved['id'] !== $catModel->id) {
                return ApiResponse::error('QR yang dipindai bukan milik kucing ini.', 422, 'QR_MISMATCH');
            }
        }

        $user = $request->user();
        $state = $this->service->checkDailyCooldown($user, $catModel);
        $encounterable = true;
        try {
            $this->service->assertCatAvailable($catModel);
        } catch (CatHunterException) {
            $encounterable = false;
        }

        $canScan = $encounterable && $state['can_scan_today'];
        $catalogItem = $this->service->formatCat($catModel->loadMissing('territory'), $this->dexFor($user->id, $catModel->id));

        return ApiResponse::success([
            'cat' => $catalogItem,
            'can_scan_today' => $canScan,
            'is_available' => $encounterable,
            'last_scanned_at' => $state['last_scanned_at'],
            'next_available_at' => $state['can_scan_today'] ? null : $state['next_available_at'],
        ], $canScan ? 'Kucing siap disapa.' : 'Kucing belum bisa disapa saat ini.');
    }

    /**
     * POST /api/v1/cat-hunter/cats/{cat}/encounters
     */
    public function completeEncounter(CompleteEncounterRequest $request, string $cat): JsonResponse
    {
        $catModel = $this->findCat($cat);
        if (!$catModel) {
            return $this->catNotFound();
        }

        try {
            $result = $this->service->completeEncounter($request->user(), $catModel);
        } catch (CatHunterException $e) {
            return $this->domainError($e);
        }

        return ApiResponse::created($result, 'Pertemuan selesai! Kucing tersimpan di Cat-Dex kamu.');
    }

    /**
     * PATCH /api/v1/cat-hunter/cats/{cat}/name
     */
    public function rename(RenameCatRequest $request, string $cat): JsonResponse
    {
        $catModel = $this->findCat($cat);
        if (!$catModel) {
            return $this->catNotFound();
        }

        try {
            $result = $this->service->renameCat($request->user(), $catModel, $request->validated('name'));
        } catch (CatHunterException $e) {
            return $this->domainError($e);
        }

        return ApiResponse::success($result, 'Nama kucing berhasil disimpan dan dikunci permanen.');
    }

    /**
     * GET /api/v1/cat-hunter/zones
     */
    public function zones(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getZonesSummary($request->user()),
            'Ringkasan zona berhasil diambil.'
        );
    }

    /**
     * GET /api/v1/cat-hunter/profile
     */
    public function profile(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->service->getUserProfileStats($request->user()),
            'Profil pemburu kucing berhasil diambil.'
        );
    }

    /**
     * PATCH /api/v1/cat-hunter/profile/avatar
     */
    public function setAvatar(SetFavoriteAvatarRequest $request): JsonResponse
    {
        try {
            $result = $this->service->setFavoriteAvatar($request->user(), $request->validated('cat_id'));
        } catch (CatHunterException $e) {
            return $this->domainError($e);
        }

        return ApiResponse::success($result, 'Avatar favorit berhasil disimpan.');
    }

    // ------------------------------------------------------------------

    /** Cari kucing berdasarkan ULID atau kode kalung (unique_code). */
    private function findCat(string $identifier): ?Cat
    {
        return Cat::where('id', $identifier)
            ->orWhereRaw('UPPER(unique_code) = ?', [strtoupper($identifier)])
            ->first();
    }

    private function dexFor(string $userId, string $catId)
    {
        return \App\Modules\CatHunter\Models\UserCatDex::where('user_id', $userId)->where('cat_id', $catId)->first();
    }

    private function catNotFound(): JsonResponse
    {
        return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
    }

    private function domainError(CatHunterException $e): JsonResponse
    {
        return ApiResponse::error($e->getMessage(), $e->status, $e->errorCode, $e->errors);
    }
}
