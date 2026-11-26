<?php

namespace App\Modules\Cat\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Services\CatCatalogPresenter;
use App\Modules\Cat\Services\QrCodeService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatController extends Controller
{
    public function __construct(
        protected QrCodeService $qrCodeService,
        protected CatCatalogPresenter $presenter,
    ) {}

    /**
     * List all campus cats (Public resource without precise coordinates or raw QR tokens).
     * GET /api/v1/cats
     */
    public function index(Request $request)
    {
        $query = Cat::query()->with(['territory', 'photos']);

        foreach (['presence_status', 'adoption_status', 'health_status', 'gender', 'age_group'] as $enumField) {
            if ($request->filled($enumField)) {
                $query->where($enumField, strtoupper((string) $request->query($enumField)));
            }
        }

        if ($request->filled('territory_id')) {
            $query->where('territory_id', (string) $request->query('territory_id'));
        }

        if ($request->has('is_sterilized') && $request->query('is_sterilized') !== '') {
            $query->where('is_sterilized', filter_var($request->query('is_sterilized'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('is_vaccinated') && $request->query('is_vaccinated') !== '') {
            $query->where('is_vaccinated', filter_var($request->query('is_vaccinated'), FILTER_VALIDATE_BOOLEAN));
        }

        $search = trim((string) ($request->query('search') ?? $request->query('q') ?? ''));
        if ($search !== '') {
            $like = "%{$search}%";
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('unique_code', 'like', $like)
                    ->orWhere('color_pattern', 'like', $like)
                    ->orWhere('primary_color', 'like', $like)
                    ->orWhere('fur_pattern', 'like', $like)
                    ->orWhere('temperament', 'like', $like);
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 15)));
        $cats = $query->orderBy('name')->paginate($perPage);
        $cats->setCollection(collect($this->presenter->mapPublicCollection($cats->getCollection())));

        return ApiResponse::paginated($cats, 'Daftar kucing berhasil diambil.');
    }

    /**
     * Show single cat profile (Public resource without private data).
     * GET /api/v1/cats/{id}
     */
    public function show(string $id)
    {
        $cat = Cat::with(['territory', 'photos'])->find($id);

        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $rarities = $this->presenter->loadGameRarities([$cat->id]);

        return ApiResponse::success(
            $this->presenter->toPublicArray($cat, $rarities[$cat->id] ?? null),
            'Detail profil kucing berhasil diambil.'
        );
    }

    /**
     * Find cat by printed collar unique code (Manual input).
     * GET /api/v1/cats/by-code/{unique_code}
     */
    public function findByCode(string $uniqueCode)
    {
        $cat = Cat::with(['territory', 'photos'])
            ->where('unique_code', strtoupper(trim($uniqueCode)))
            ->first();

        if (!$cat) {
            return ApiResponse::error('Kucing dengan kode tersebut tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $rarities = $this->presenter->loadGameRarities([$cat->id]);

        return ApiResponse::success(
            $this->presenter->toPublicArray($cat, $rarities[$cat->id] ?? null),
            'Kucing berhasil ditemukan.'
        );
    }

    /**
     * Stream public cat reference photo.
     * GET /api/v1/cats/{id}/photos/{mediaId}
     */
    public function servePhoto(string $id, string $mediaId)
    {
        $cat = Cat::find($id);
        if (!$cat) {
            abort(404, 'Kucing tidak ditemukan.');
        }

        $isAttached = $cat->primary_photo_media_id === $mediaId
            || DB::table('cat_photos')
                ->where('cat_id', $cat->id)
                ->where('media_id', $mediaId)
                ->exists();

        if (!$isAttached) {
            abort(404, 'Foto kucing tidak ditemukan.');
        }

        $media = DB::table('media_files')
            ->where('id', $mediaId)
            ->whereNull('deleted_at')
            ->first();

        if (!$media || !Storage::disk($media->disk)->exists($media->path)) {
            abort(404, 'Berkas foto tidak tersedia.');
        }

        return Storage::disk($media->disk)->response($media->path, null, [
            'Content-Type' => $media->mime ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Action hub provider for cat profile page (PRD 4.3).
     * GET /api/v1/cats/{id}/actions
     */
    public function actions(Request $request, string $id)
    {
        $cat = Cat::find($id);

        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $user = $request->user();
        $actions = [];

        // 1. Encounter Action (Cat Hunter)
        $canEncounter = ($cat->presence_status === 'ON_CAMPUS') &&
                         !in_array($cat->health_status, ['SICK', 'INJURED'], true);
        $actions[] = [
            'id' => 'game.encounter',
            'module' => 'CatHunter',
            'label' => 'Dekati & Elus',
            'enabled' => $canEncounter,
            'reason' => $canEncounter ? null : 'Kucing sedang tidak berada di kampus atau sedang dalam perawatan sakit/terluka.',
        ];

        // 2. Adoption Action (Cat Adoption)
        $canAdopt = ($cat->adoption_status === 'READY') && ($user?->user_type === 'MAHASISWA');
        $actions[] = [
            'id' => 'adoption.apply',
            'module' => 'CatAdoption',
            'label' => 'Ajukan Adopsi',
            'enabled' => $canAdopt,
            'reason' => $canAdopt ? null : ($user?->user_type !== 'MAHASISWA' ? 'Hanya mahasiswa terverifikasi yang dapat mengajukan adopsi.' : 'Kucing ini belum berstatus siap adopsi.'),
        ];

        // 3. Incident Report Action (Always available)
        $actions[] = [
            'id' => 'incident.report',
            'module' => 'IncidentReport',
            'label' => 'Laporkan Insiden/Kondisi',
            'enabled' => true,
            'reason' => null,
        ];

        // 4. Feeding Action (Delegated to FeedingActionProvider per PRD 4.3 & MEOW-23)
        if (class_exists(\App\Modules\FeedingSpot\Services\FeedingActionProvider::class)) {
            $feedingProvider = app(\App\Modules\FeedingSpot\Services\FeedingActionProvider::class);
            $feedingActions = $feedingProvider->actionsFor($cat, $user);
            $actions = array_merge($actions, $feedingActions);
        } else {
            $actions[] = [
                'id' => 'feeding.start',
                'module' => 'FeedingSpot',
                'label' => 'Beri Makan di Spot',
                'enabled' => $cat->presence_status === 'ON_CAMPUS',
                'reason' => null,
            ];
        }

        return ApiResponse::success($actions, 'Daftar aksi hub profil kucing berhasil diambil.');
    }

    /**
     * Record sighting for this cat (PRD 4.4).
     * POST /api/v1/cats/{id}/sightings (Idempotency Key required)
     */
    public function recordSighting(Request $request, string $id)
    {
        $cat = Cat::find($id);
        if (!$cat) {
            return ApiResponse::error('Kucing tidak ditemukan.', 404, 'NOT_FOUND');
        }

        $user = $request->user();

        // 30-minute deduplication check
        $recentSighting = DB::table('cat_sightings')
            ->where('cat_id', $cat->id)
            ->where('user_id', $user->id)
            ->where('occurred_at', '>=', now()->subMinutes(30))
            ->first();

        if ($recentSighting) {
            return ApiResponse::success([
                'sighting_id' => $recentSighting->id,
                'deduplicated' => true,
            ], 'Perjumpaan sudah tercatat dalam 30 menit terakhir.');
        }

        $sightingId = (string) Str::ulid();
        DB::table('cat_sightings')->insert([
            'id' => $sightingId,
            'cat_id' => $cat->id,
            'user_id' => $user->id,
            'source' => $request->input('source', 'manual_sighting'),
            'request_id' => $request->header('Idempotency-Key'),
            'location_lat' => $request->input('location_lat'),
            'location_lng' => $request->input('location_lng'),
            'accuracy_m' => $request->input('accuracy_m'),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ApiResponse::created([
            'sighting_id' => $sightingId,
            'deduplicated' => false,
        ], 'Perjumpaan berhasil dicatat.');
    }
}
