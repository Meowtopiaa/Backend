<?php

namespace App\Modules\Cat\Services;

use App\Modules\Cat\Models\Cat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presenter DTO resmi untuk master katalog `cats`.
 * Memisahkan respons publik (tanpa token QR mentah & koordinat presisi)
 * dari respons Admin/Kurator sesuai PRD Bagian 4.2 & Aturan Keras #9.
 */
class CatCatalogPresenter
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * Ambil peta rarity dari tabel satelit `game_cat_profiles` (milik CatHunter)
     * tanpa menduplikasi kolom di tabel `cats`.
     *
     * @param  array<int, string>  $catIds
     * @return array<string, string>
     */
    public function loadGameRarities(array $catIds): array
    {
        if (empty($catIds) || !Schema::hasTable('game_cat_profiles')) {
            return [];
        }

        return DB::table('game_cat_profiles')
            ->whereIn('cat_id', $catIds)
            ->pluck('rarity', 'cat_id')
            ->all();
    }

    /**
     * Format profil publik kucing yang aman dikonsumsi modul lain
     * (Enigma, Game, Rekans Caps, Optimind) tanpa data privat atau token QR mentah.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(Cat $cat, ?string $gameRarity = null): array
    {
        $primaryColor = $cat->primary_color;
        $furPattern = $cat->fur_pattern;
        $colorPattern = $cat->color_pattern;
        if (($colorPattern === null || trim($colorPattern) === '') && ($primaryColor || $furPattern)) {
            $colorPattern = trim(($primaryColor ?? '') . ' ' . ($furPattern ?? ''));
        }

        $photoUrl = $cat->primary_photo_media_id
            ? route('cats.photo', ['id' => $cat->id, 'mediaId' => $cat->primary_photo_media_id], false)
            : null;

        $photos = $cat->relationLoaded('photos')
            ? $cat->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'media_id' => $photo->media_id,
                'angle' => $photo->angle,
                'sort_order' => $photo->sort_order,
                'url' => route('cats.photo', ['id' => $cat->id, 'mediaId' => $photo->media_id], false),
            ])->values()->all()
            : [];

        $territory = $cat->relationLoaded('territory') && $cat->territory
            ? [
                'id' => $cat->territory->id,
                'code' => $cat->territory->code,
                'name' => $cat->territory->name,
            ]
            : null;

        return [
            'id' => $cat->id,
            'unique_code' => $cat->unique_code,
            'name' => $cat->name,
            'gender' => $cat->gender,
            'primary_color' => $primaryColor,
            'fur_pattern' => $furPattern,
            'color_pattern' => $colorPattern,
            'age_group' => $cat->age_group,
            'is_sterilized' => $cat->is_sterilized,
            'is_vaccinated' => $cat->is_vaccinated,
            'vaccinated_at' => $cat->vaccinated_at?->toDateString(),
            'vaccination_notes' => $cat->vaccination_notes,
            'description' => $cat->description,
            'temperament' => $cat->temperament,
            'special_needs' => $cat->special_needs,
            'traits' => $cat->traits ?? (object) [],
            'territory_id' => $cat->territory_id,
            'territory' => $territory,
            'primary_photo_media_id' => $cat->primary_photo_media_id,
            'photo_url' => $photoUrl,
            'photos' => $photos,
            'presence_status' => $cat->presence_status,
            'health_status' => $cat->health_status,
            'adoption_status' => $cat->adoption_status,
            'verification_status' => $cat->verification_status,
            'game_rarity' => $gameRarity,
            'first_seen_at' => $cat->first_seen_at?->toIso8601String(),
            'last_seen_at' => $cat->last_seen_at?->toIso8601String(),
            'created_at' => $cat->created_at?->toIso8601String(),
            'updated_at' => $cat->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Format detail admin/kurator (memuat token QR aktif MTP1.C.<token> dan riwayat status).
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(Cat $cat, ?string $gameRarity = null): array
    {
        $base = $this->toPublicArray($cat, $gameRarity);
        $activeToken = $cat->relationLoaded('activeQrToken')
            ? $cat->activeQrToken
            : $cat->activeQrToken()->first();

        $base['qr_status'] = $activeToken?->status ?? 'NONE';
        $base['qr_payload'] = $activeToken
            ? $this->qrCodeService->formatQrPayload($activeToken->token)
            : null;
        $base['qr_issued_at'] = $activeToken?->issued_at?->toIso8601String();
        $base['deleted_at'] = $cat->deleted_at?->toIso8601String();

        if ($cat->relationLoaded('statusLogs')) {
            $base['status_logs'] = $cat->statusLogs->map(fn ($log) => [
                'id' => $log->id,
                'dimension' => $log->dimension,
                'from_value' => $log->from_value,
                'to_value' => $log->to_value,
                'reason' => $log->reason,
                'source_module' => $log->source_module,
                'actor_id' => $log->actor_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $base;
    }

    /**
     * @param  Collection<int, Cat>  $cats
     * @return array<int, array<string, mixed>>
     */
    public function mapPublicCollection(Collection $cats): array
    {
        $rarities = $this->loadGameRarities($cats->pluck('id')->all());

        return $cats->map(
            fn (Cat $cat) => $this->toPublicArray($cat, $rarities[$cat->id] ?? null)
        )->values()->all();
    }

    /**
     * @param  Collection<int, Cat>  $cats
     * @return array<int, array<string, mixed>>
     */
    public function mapAdminCollection(Collection $cats): array
    {
        $rarities = $this->loadGameRarities($cats->pluck('id')->all());

        return $cats->map(
            fn (Cat $cat) => $this->toAdminArray($cat, $rarities[$cat->id] ?? null)
        )->values()->all();
    }
}
