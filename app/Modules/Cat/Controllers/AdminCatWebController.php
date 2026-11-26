<?php

namespace App\Modules\Cat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Models\Territory;
use App\Modules\Cat\Services\CatCatalogPresenter;
use App\Modules\Cat\Services\CatManagementService;
use App\Modules\Cat\Services\QrCodeService;
use App\Modules\FeedingSpot\Models\FeedingSpot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminCatWebController extends Controller
{
    public function __construct(
        private readonly CatManagementService $catService,
        private readonly QrCodeService $qrCodeService,
        private readonly CatCatalogPresenter $presenter,
    ) {}

    public function index(Request $request)
    {
        Auth::shouldUse('web');
        $user = Auth::guard('web')->user();
        if (!$user || (!$user->can('cat.edit') && !$user->can('cat.curate'))) {
            abort(403, 'Anda tidak memiliki izin mengelola katalog kucing.');
        }

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'health_status' => strtoupper(trim((string) $request->query('health_status', ''))),
            'adoption_status' => strtoupper(trim((string) $request->query('adoption_status', ''))),
            'presence_status' => strtoupper(trim((string) $request->query('presence_status', ''))),
            'territory_id' => trim((string) $request->query('territory_id', '')),
            'is_sterilized' => $request->query('is_sterilized', ''),
            'is_vaccinated' => $request->query('is_vaccinated', ''),
            'include_deleted' => $request->boolean('include_deleted'),
        ];

        $query = Cat::query()->with(['territory', 'photos', 'activeQrToken']);
        if ($filters['include_deleted']) {
            $query->withTrashed();
        }
        if ($filters['health_status'] === 'NEEDS_CARE') {
            $query->whereIn('health_status', ['SICK', 'INJURED', 'UNDER_CARE']);
        } elseif ($filters['health_status'] !== '') {
            $query->where('health_status', $filters['health_status']);
        }
        foreach (['adoption_status', 'presence_status'] as $enumField) {
            if ($filters[$enumField] !== '') {
                $query->where($enumField, $filters[$enumField]);
            }
        }
        if ($filters['territory_id'] !== '') {
            $query->where('territory_id', $filters['territory_id']);
        }
        if ($filters['is_sterilized'] !== '' && $filters['is_sterilized'] !== null) {
            $query->where('is_sterilized', filter_var($filters['is_sterilized'], FILTER_VALIDATE_BOOLEAN));
        }
        if ($filters['is_vaccinated'] !== '' && $filters['is_vaccinated'] !== null) {
            $query->where('is_vaccinated', filter_var($filters['is_vaccinated'], FILTER_VALIDATE_BOOLEAN));
        }
        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($like) {
                $q->where('id', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('unique_code', 'like', $like)
                    ->orWhere('color_pattern', 'like', $like)
                    ->orWhere('primary_color', 'like', $like)
                    ->orWhere('fur_pattern', 'like', $like)
                    ->orWhere('temperament', 'like', $like);
            });
        }

        $cats = $query->orderByDesc('created_at')->orderBy('name')->paginate(10)->withQueryString();
        $pageCats = $cats->getCollection();
        $rarities = $this->presenter->loadGameRarities($pageCats->pluck('id')->all());

        // Pastikan setiap kucing aktif pada halaman ini punya token QR agar pratinjau akrilik selalu siap
        $qrPreviewMap = [];
        $logoDataUri = $this->resolveLogoDataUri();
        $backAcrylicSvg = $this->qrCodeService->renderAcrylicBackSvg($logoDataUri, true);

        foreach ($pageCats as $cat) {
            $token = $cat->activeQrToken;
            if (!$token && !$cat->trashed()) {
                $token = $this->catService->ensureActiveQrToken($cat, $user);
                $cat->setRelation('activeQrToken', $token);
            }
            if ($token) {
                $qrPreviewMap[$cat->id] = [
                    'payload' => $this->qrCodeService->formatQrPayload($token->token),
                    'front_svg' => $this->qrCodeService->renderAcrylicFrontSvg($cat, $token->token, true),
                    'issued_at' => $token->issued_at?->timezone('Asia/Jakarta')->format('d M Y, H:i') . ' WIB',
                ];
            }
        }

        $stats = [
            'total' => Cat::count(),
            'ready_adoption' => Cat::where('adoption_status', 'READY')->where('presence_status', 'ON_CAMPUS')->count(),
            'needs_care' => Cat::whereIn('health_status', ['SICK', 'INJURED', 'UNDER_CARE'])->count(),
            'sterilized' => Cat::where('is_sterilized', true)->count(),
            'vaccinated' => Cat::where('is_vaccinated', true)->count(),
            'inactive' => Cat::onlyTrashed()->count(),
        ];

        $territories = Territory::orderBy('name')->get();
        $allSpotsCount = FeedingSpot::count();

        return view('cat::admin.catalog', [
            'cats' => $cats,
            'rarities' => $rarities,
            'qrPreviewMap' => $qrPreviewMap,
            'backAcrylicSvg' => $backAcrylicSvg,
            'stats' => $stats,
            'filters' => $filters,
            'territories' => $territories,
            'allSpotsCount' => $allSpotsCount,
            'canManageSpots' => $user->can('spot.manage'),
            'canReviewFeeding' => $user->can('feeding.review'),
            'canRegenerateQr' => $user->can('cat.qr.regenerate'),
        ]);
    }

    public function store(Request $request)
    {
        Auth::shouldUse('web');
        $user = Auth::guard('web')->user();

        $validated = $this->validateCatForm($request);

        try {
            $cat = $this->catService->createCat($validated, $user, $request->file('photo'));
        } catch (ConflictHttpException $e) {
            return redirect('/admin/cats?error=' . urlencode($e->getMessage()));
        }

        return redirect('/admin/cats?notice=' . urlencode("Kucing {$cat->name} ({$cat->unique_code}) berhasil ditambahkan beserta QR kalung aktif.") . '&created_cat=' . urlencode($cat->id));
    }

    public function update(Request $request, string $id)
    {
        Auth::shouldUse('web');
        $user = Auth::guard('web')->user();

        $cat = Cat::findOrFail($id);
        $validated = $this->validateCatForm($request, $cat->id);

        try {
            $this->catService->updateCat($cat, $validated, $user, $request->file('photo'));
        } catch (ConflictHttpException $e) {
            return redirect('/admin/cats?error=' . urlencode($e->getMessage()));
        }

        return redirect('/admin/cats?notice=' . urlencode("Profil kucing {$cat->name} ({$cat->unique_code}) berhasil diperbarui."));
    }

    public function destroy(Request $request, string $id)
    {
        Auth::shouldUse('web');
        $cat = Cat::findOrFail($id);
        $this->catService->softDeleteCat($cat, Auth::guard('web')->user(), $request->input('reason'));

        return redirect('/admin/cats?notice=' . urlencode("Kucing {$cat->name} ({$cat->unique_code}) dinonaktifkan dari katalog aktif."));
    }

    public function restore(string $id)
    {
        Auth::shouldUse('web');
        $cat = Cat::withTrashed()->findOrFail($id);
        $this->catService->restoreCat($cat, Auth::guard('web')->user());

        return redirect('/admin/cats?notice=' . urlencode("Kucing {$cat->name} ({$cat->unique_code}) dipulihkan ke katalog aktif."));
    }

    public function regenerateQr(Request $request, string $id)
    {
        Auth::shouldUse('web');
        $cat = Cat::findOrFail($id);
        $this->catService->regenerateQrToken($cat, Auth::guard('web')->user(), $request->input('reason'));

        return redirect('/admin/cats?notice=' . urlencode("QR kalung {$cat->name} ({$cat->unique_code}) berhasil diperbarui; token lama dicabut.") . '&regenerated_cat=' . urlencode($cat->id));
    }

    public function printQrSheet(Request $request, string $id)
    {
        Auth::shouldUse('web');
        $cat = Cat::with(['territory', 'activeQrToken'])->findOrFail($id);
        $token = $this->catService->ensureActiveQrToken($cat, Auth::guard('web')->user());
        $showCutline = $request->boolean('cutline', true);
        $logoDataUri = $this->resolveLogoDataUri();

        return view('cat::admin.acrylic-qr-sheet', [
            'singleCat' => $cat,
            'items' => [[
                'cat' => $cat,
                'payload' => $this->qrCodeService->formatQrPayload($token->token),
                'front_svg' => $this->qrCodeService->renderAcrylicFrontSvg($cat, $token->token, $showCutline),
                'back_svg' => $this->qrCodeService->renderAcrylicBackSvg($logoDataUri, $showCutline),
            ]],
            'showCutline' => $showCutline,
            'scaleMode' => $request->query('scale', 'actual'),
        ]);
    }

    public function printAllQrSheets(Request $request)
    {
        Auth::shouldUse('web');
        $user = Auth::guard('web')->user();
        $cats = Cat::with(['territory', 'activeQrToken'])->orderBy('unique_code')->get();
        $showCutline = $request->boolean('cutline', true);
        $logoDataUri = $this->resolveLogoDataUri();
        $backSvg = $this->qrCodeService->renderAcrylicBackSvg($logoDataUri, $showCutline);

        $items = [];
        foreach ($cats as $cat) {
            $token = $this->catService->ensureActiveQrToken($cat, $user);
            $items[] = [
                'cat' => $cat,
                'payload' => $this->qrCodeService->formatQrPayload($token->token),
                'front_svg' => $this->qrCodeService->renderAcrylicFrontSvg($cat, $token->token, $showCutline),
                'back_svg' => $backSvg,
            ];
        }

        return view('cat::admin.acrylic-qr-sheet', [
            'singleCat' => null,
            'items' => $items,
            'showCutline' => $showCutline,
            'scaleMode' => $request->query('scale', 'actual'),
        ]);
    }

    public function downloadQr(Request $request, string $id): Response
    {
        Auth::shouldUse('web');
        $cat = Cat::findOrFail($id);
        $token = $this->catService->ensureActiveQrToken($cat, Auth::guard('web')->user());
        $format = strtolower((string) $request->query('format', 'acrylic_front'));
        $safeCode = preg_replace('/[^A-Za-z0-9\-_]/', '', $cat->unique_code) ?: $cat->id;

        if ($format === 'png') {
            return response($this->qrCodeService->renderQrPng($token->token, 12, 4), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="qr-' . $safeCode . '.png"',
            ]);
        }
        if ($format === 'svg') {
            return response($this->qrCodeService->renderQrSvg($token->token, 360, 4), 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="qr-' . $safeCode . '.svg"',
            ]);
        }
        if ($format === 'acrylic_back') {
            return response($this->qrCodeService->renderAcrylicBackSvg($this->resolveLogoDataUri()), 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="tag-akrilik-belakang-meowtopia.svg"',
            ]);
        }

        return response($this->qrCodeService->renderAcrylicFrontSvg($cat, $token->token), 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tag-akrilik-depan-' . $safeCode . '.svg"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCatForm(Request $request, ?string $ignoreCatId = null): array
    {
        $uniqueCodeRule = $ignoreCatId
            ? Rule::unique('cats', 'unique_code')->ignore($ignoreCatId, 'id')
            : Rule::unique('cats', 'unique_code');

        $validated = $request->validate([
            'unique_code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-_]+$/', $uniqueCodeRule],
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'string', Rule::in(Cat::GENDERS)],
            'age_group' => ['required', 'string', Rule::in(Cat::AGE_GROUPS)],
            'primary_color' => ['nullable', 'string', 'max:80'],
            'fur_pattern' => ['nullable', 'string', 'max:80'],
            'color_pattern' => ['nullable', 'string', 'max:160'],
            'is_sterilized' => ['nullable', 'in:,0,1'],
            'is_vaccinated' => ['nullable', 'in:,0,1'],
            'vaccinated_at' => ['nullable', 'date'],
            'vaccination_notes' => ['nullable', 'string', 'max:1000'],
            'temperament' => ['nullable', 'string', 'max:160'],
            'special_needs' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'territory_id' => ['nullable', 'string', 'size:26', 'exists:territories,id'],
            'presence_status' => ['required', 'string', Rule::in(Cat::PRESENCE_STATUSES)],
            'health_status' => ['required', 'string', Rule::in(Cat::HEALTH_STATUSES)],
            'adoption_status' => ['required', 'string', Rule::in(Cat::ADOPTION_STATUSES)],
            'status_reason' => ['nullable', 'string', 'max:500'],
            'traits_physical_marks' => ['nullable', 'string', 'max:255'],
            'traits_ear_tip' => ['nullable', 'string', 'max:80'],
            'traits_tail_type' => ['nullable', 'string', 'max:80'],
            'traits_eye_color' => ['nullable', 'string', 'max:80'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        // Normalisasi tri-state boolean ('', '0', '1') -> null/false/true
        foreach (['is_sterilized', 'is_vaccinated'] as $boolField) {
            $raw = $validated[$boolField] ?? '';
            $validated[$boolField] = $raw === '' ? null : ($raw === '1');
        }

        // Gabungkan traits ciri fisik terstruktur (Game & Rekans Caps)
        $traits = array_filter([
            'physical_marks' => trim((string) ($validated['traits_physical_marks'] ?? '')),
            'ear_tip' => trim((string) ($validated['traits_ear_tip'] ?? '')),
            'tail_type' => trim((string) ($validated['traits_tail_type'] ?? '')),
            'eye_color' => trim((string) ($validated['traits_eye_color'] ?? '')),
        ], fn ($v) => $v !== '');
        $validated['traits'] = $traits;

        unset(
            $validated['traits_physical_marks'],
            $validated['traits_ear_tip'],
            $validated['traits_tail_type'],
            $validated['traits_eye_color']
        );

        return $validated;
    }

    private function resolveLogoDataUri(): ?string
    {
        $logoPath = app_path('Modules/FeedingSpot/resources/assets/meowtopia-logo.jpg');
        if (!is_file($logoPath)) {
            return null;
        }
        $raw = @file_get_contents($logoPath);

        return $raw === false ? null : ('data:image/jpeg;base64,' . base64_encode($raw));
    }
}
