<?php

namespace App\Modules\Cat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Requests\StoreCatRequest;
use App\Modules\Cat\Requests\UpdateCatRequest;
use App\Modules\Cat\Services\CatManagementService;
use App\Modules\Cat\Services\QrCodeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminCatController extends Controller
{
    public function __construct(
        private readonly CatManagementService $catService,
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * GET /api/v1/admin/cats
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(Cat::GENDERS)],
            'age_group' => ['nullable', 'string', Rule::in(Cat::AGE_GROUPS)],
            'presence_status' => ['nullable', 'string', Rule::in(Cat::PRESENCE_STATUSES)],
            'health_status' => ['nullable', 'string', Rule::in(Cat::HEALTH_STATUSES)],
            'adoption_status' => ['nullable', 'string', Rule::in(Cat::ADOPTION_STATUSES)],
            'territory_id' => ['nullable', 'string', 'size:26'],
            'is_sterilized' => ['nullable'],
            'is_vaccinated' => ['nullable'],
            'include_deleted' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->catService->paginateAdminCats(
            $validated,
            (int) ($validated['per_page'] ?? 15)
        );

        return ApiResponse::paginated($paginator, 'Daftar katalog kucing admin berhasil diambil.');
    }

    /**
     * GET /api/v1/admin/cats/{id}
     */
    public function show(string $id): JsonResponse
    {
        $cat = Cat::withTrashed()->where('id', $id)->orWhere('unique_code', strtoupper(trim($id)))->first();
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        return ApiResponse::success(
            $this->catService->getAdminCatDetail($cat),
            'Detail katalog kucing admin berhasil diambil.'
        );
    }

    /**
     * POST /api/v1/admin/cats († Idempotency-Key required)
     */
    public function store(StoreCatRequest $request): JsonResponse
    {
        try {
            $cat = $this->catService->createCat(
                $request->validated(),
                $request->user(),
                $request->file('photo')
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), 409, 'INVALID_STATUS_TRANSITION');
        }

        return ApiResponse::created(
            $this->catService->getAdminCatDetail($cat),
            'Kucing baru berhasil ditambahkan ke katalog beserta QR kalung aktif.'
        );
    }

    /**
     * PUT / PATCH /api/v1/admin/cats/{id}
     */
    public function update(UpdateCatRequest $request, string $id): JsonResponse
    {
        $cat = Cat::find($id);
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        try {
            $updated = $this->catService->updateCat(
                $cat,
                $request->validated(),
                $request->user(),
                $request->file('photo')
            );
        } catch (ConflictHttpException $e) {
            return ApiResponse::error($e->getMessage(), 409, 'INVALID_STATUS_TRANSITION');
        }

        return ApiResponse::success(
            $this->catService->getAdminCatDetail($updated),
            'Data katalog kucing berhasil diperbarui.'
        );
    }

    /**
     * POST /api/v1/admin/cats/{id}/photos
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $cat = Cat::find($id);
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $validated = $request->validate([
            'photo' => ['required', 'file', 'image', 'max:5120'],
            'angle' => ['nullable', 'string', Rule::in(['front', 'left', 'right', 'tail', 'face'])],
            'make_primary' => ['nullable', 'boolean'],
        ]);

        $catPhoto = $this->catService->attachUploadedPhoto(
            $cat,
            $request->file('photo'),
            $request->user(),
            $validated['angle'] ?? 'front',
            (bool) ($validated['make_primary'] ?? false)
        );

        return ApiResponse::created([
            'id' => $catPhoto->id,
            'cat_id' => $cat->id,
            'media_id' => $catPhoto->media_id,
            'angle' => $catPhoto->angle,
            'url' => route('cats.photo', ['id' => $cat->id, 'mediaId' => $catPhoto->media_id], false),
        ], 'Foto referensi kucing berhasil diunggah.');
    }

    /**
     * DELETE /api/v1/admin/cats/{id} (Soft delete)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $cat = Cat::find($id);
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->catService->softDeleteCat($cat, $request->user(), $validated['reason'] ?? null);

        return ApiResponse::success([
            'id' => $cat->id,
            'deleted_at' => $cat->fresh()?->deleted_at?->toIso8601String(),
        ], 'Kucing berhasil dinonaktifkan dari katalog aktif tanpa menghapus riwayat.');
    }

    /**
     * POST /api/v1/admin/cats/{id}/restore
     */
    public function restore(Request $request, string $id): JsonResponse
    {
        $cat = Cat::withTrashed()->find($id);
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $restored = $this->catService->restoreCat($cat, $request->user());

        return ApiResponse::success(
            $this->catService->getAdminCatDetail($restored),
            'Data kucing berhasil dipulihkan ke katalog aktif.'
        );
    }

    /**
     * POST /api/v1/admin/cats/{id}/qr/regenerate
     * Permission: cat.qr.regenerate
     */
    public function regenerateQr(Request $request, string $id): JsonResponse
    {
        $cat = Cat::find($id);
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newToken = $this->catService->regenerateQrToken(
            $cat,
            $request->user(),
            $validated['reason'] ?? null
        );

        return ApiResponse::success([
            'cat_id' => $cat->id,
            'unique_code' => $cat->unique_code,
            'token_id' => $newToken->id,
            'qr_status' => $newToken->status,
            'qr_payload' => $this->qrCodeService->formatQrPayload($newToken->token),
            'issued_at' => $newToken->issued_at?->toIso8601String(),
        ], 'Token QR kalung kucing berhasil diperbarui dan token lama telah dicabut.');
    }

    /**
     * GET /api/v1/admin/cats/{id}/qr-download?format=svg|png|acrylic_front|acrylic_back
     */
    public function downloadQr(Request $request, string $id): Response
    {
        $cat = Cat::find($id);
        if (!$cat) {
            abort(404, 'Kucing tidak ditemukan.');
        }

        $activeToken = $this->catService->ensureActiveQrToken($cat, $request->user());
        $format = strtolower((string) $request->query('format', 'acrylic_front'));
        $safeCode = preg_replace('/[^A-Za-z0-9\-_]/', '', $cat->unique_code) ?: $cat->id;

        if ($format === 'png') {
            $png = $this->qrCodeService->renderQrPng($activeToken->token, 12, 4);

            return response($png, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="qr-' . $safeCode . '.png"',
            ]);
        }

        if ($format === 'svg') {
            $svg = $this->qrCodeService->renderQrSvg($activeToken->token, 360, 4);

            return response($svg, 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="qr-' . $safeCode . '.svg"',
            ]);
        }

        if ($format === 'acrylic_back') {
            $svg = $this->qrCodeService->renderAcrylicBackSvg($this->resolveLogoDataUri());

            return response($svg, 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="tag-akrilik-belakang-meowtopia.svg"',
            ]);
        }

        $svg = $this->qrCodeService->renderAcrylicFrontSvg($cat, $activeToken->token);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tag-akrilik-depan-' . $safeCode . '.svg"',
        ]);
    }

    private function resolveLogoDataUri(): ?string
    {
        $logoPath = app_path('Modules/FeedingSpot/resources/assets/meowtopia-logo.jpg');
        if (!is_file($logoPath)) {
            return null;
        }
        $raw = @file_get_contents($logoPath);
        if ($raw === false) {
            return null;
        }

        return 'data:image/jpeg;base64,' . base64_encode($raw);
    }
}
