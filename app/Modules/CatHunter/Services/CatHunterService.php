<?php

namespace App\Modules\CatHunter\Services;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Models\Territory;
use App\Modules\CatHunter\Exceptions\CatHunterException;
use App\Modules\CatHunter\Models\CatEncounter;
use App\Modules\CatHunter\Models\UserCatDex;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logika game CatHunter.
 *
 * Aturan: tanpa poin (PointService tidak dipakai), tanpa kuis/kondisi fisik/suvenir.
 * Data fisik & medis kucing dari tabel `cats` hanya dibaca.
 */
class CatHunterService
{
    public const TIMEZONE = 'Asia/Jakarta';
    public const RENAME_REQUIRED_ENCOUNTERS = 5;
    public const NAME_MIN_LENGTH = 2;
    public const NAME_MAX_LENGTH = 20;
    public const DEFAULT_QUOTE = 'Meoong... senangnya dikunjungi!';

    public const TITLE_NEWCOMER = 'Pendatang Baru';
    public const TITLE_FRIEND = 'Sahabat Kucing Kampus';
    public const TITLE_MASTER = 'Pawang Kucing Tel-U';

    private const CAFE_POSES = ['pillow', 'box', 'tree', 'carpet', 'stool'];

    /**
     * 4 zona wilayah kampus. Kunci = kode zona; `territory_codes` adalah kode pada tabel
     * `territories` yang dipetakan ke zona tersebut.
     */
    public const ZONES = [
        'TULT_ASRAMA' => [
            'name' => 'TULT & Asrama',
            'short_label' => 'TULT',
            'description' => 'Area gedung TULT dan kompleks asrama, rumah bagi kucing-kucing ramah di lobby dan gazebo.',
            'landmark' => 'Telkom University Landmark Tower (TULT)',
            'territory_codes' => ['TULT_ASRAMA', 'TULT', 'ASRAMA_PA', 'ASRAMA_PI'],
        ],
        'GKU' => [
            'name' => 'GKU',
            'short_label' => 'GKU',
            'description' => 'Sekitar Gedung Kuliah Umum, tempat kucing berteduh di koridor dan teras.',
            'landmark' => 'Gedung Kuliah Umum (GKU)',
            'territory_codes' => ['GKU'],
        ],
        'CACUK' => [
            'name' => 'Cacuk',
            'short_label' => 'Cacuk',
            'description' => 'Kawasan Cacuk dan jalur jogging di tepian danau.',
            'landmark' => 'Gedung Cacuk',
            'territory_codes' => ['CACUK'],
        ],
        'FAKULTAS_DEPAN' => [
            'name' => 'Fakultas Depan',
            'short_label' => 'Fakultas',
            'description' => 'Kompleks fakultas bagian depan kampus dengan banyak pohon rindang.',
            'landmark' => 'Kompleks Fakultas Depan',
            'territory_codes' => ['FAKULTAS_DEPAN', 'FIT'],
        ],
    ];

    // ------------------------------------------------------------------
    // Cooldown & encounter
    // ------------------------------------------------------------------

    /**
     * Cek apakah user sudah menyelesaikan sesi dengan kucing ini pada hari kalender ini (Asia/Jakarta).
     */
    public function checkDailyCooldown(User $user, Cat $cat): array
    {
        $dex = UserCatDex::where('user_id', $user->id)->where('cat_id', $cat->id)->first();

        return $this->cooldownState($dex?->last_scanned_at);
    }

    /**
     * Selesaikan satu sesi pertemuan (2 fase mengelus selesai).
     * Seluruh perubahan terjadi dalam satu transaksi; respons dibuat setelah data tersimpan.
     */
    public function completeEncounter(User $user, Cat $cat): array
    {
        $this->assertCatAvailable($cat);

        $result = DB::transaction(function () use ($user, $cat) {
            $this->ensureDexRow($user, $cat);

            /** @var UserCatDex $dex */
            $dex = UserCatDex::where('user_id', $user->id)
                ->where('cat_id', $cat->id)
                ->lockForUpdate()
                ->firstOrFail();

            $state = $this->cooldownState($dex->last_scanned_at);
            if (!$state['can_scan_today']) {
                throw new CatHunterException(
                    'Kamu sudah menyapa kucing ini hari ini. Kembali lagi besok ya!',
                    'DAILY_COOLDOWN',
                    409,
                    ['next_available_at' => $state['next_available_at']]
                );
            }

            $now = now(self::TIMEZONE);
            $isFirst = $dex->encounter_count === 0;

            $dex->encounter_count = $dex->encounter_count + 1;
            $dex->last_scanned_at = $now;
            $dex->save();

            CatEncounter::create([
                'user_id' => $user->id,
                'cat_id' => $cat->id,
                'scanned_at' => $now,
            ]);

            // Keaktifan populasi: catat kapan kucing terakhir terlihat (bukan status).
            Cat::where('id', $cat->id)->update([
                'last_seen_at' => $now,
                'first_seen_at' => $cat->first_seen_at ?? $now,
            ]);

            return [$dex, $isFirst];
        });

        [$dex, $isFirst] = $result;
        $cat->refresh();

        return [
            'cat_id' => $cat->id,
            'encounter_count' => $dex->encounter_count,
            'is_first_encounter' => $isFirst,
            'last_scanned_at' => $this->iso($dex->last_scanned_at),
            'can_rename' => $this->canRename($dex->encounter_count, $cat),
            'rename_required_encounters' => self::RENAME_REQUIRED_ENCOUNTERS,
            'is_name_locked' => (bool) $cat->is_name_locked,
            'cat' => $this->formatCat($cat->loadMissing('territory'), $dex),
        ];
    }

    // ------------------------------------------------------------------
    // Hak nama permanen
    // ------------------------------------------------------------------

    public function renameCat(User $user, Cat $cat, string $newName): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $newName) ?? '');
        $length = mb_strlen($name);

        if ($length < self::NAME_MIN_LENGTH || $length > self::NAME_MAX_LENGTH) {
            throw new CatHunterException(
                'Panjang nama harus ' . self::NAME_MIN_LENGTH . 'â€“' . self::NAME_MAX_LENGTH . ' karakter.',
                'VALIDATION_ERROR',
                422,
                ['name' => ['Panjang nama harus ' . self::NAME_MIN_LENGTH . 'â€“' . self::NAME_MAX_LENGTH . ' karakter.']]
            );
        }

        return DB::transaction(function () use ($user, $cat, $name) {
            /** @var Cat $lockedCat */
            $lockedCat = Cat::whereKey($cat->id)->lockForUpdate()->firstOrFail();
            $dex = UserCatDex::where('user_id', $user->id)->where('cat_id', $cat->id)->first();

            if ($lockedCat->is_name_locked) {
                throw new CatHunterException('Nama kucing ini sudah dikunci permanen.', 'NAME_ALREADY_LOCKED', 409);
            }

            if (!$dex || $dex->encounter_count < self::RENAME_REQUIRED_ENCOUNTERS) {
                throw new CatHunterException(
                    'Kamu perlu minimal ' . self::RENAME_REQUIRED_ENCOUNTERS . 'x pertemuan dengan kucing ini untuk memberi nama permanen.',
                    'RENAME_NOT_ALLOWED',
                    403
                );
            }

            $taken = Cat::withTrashed()
                ->where('id', '!=', $lockedCat->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($taken) {
                throw new CatHunterException(
                    'Nama ini sudah dipakai kucing lain, pilih nama lain ya!',
                    'NAME_TAKEN',
                    422,
                    ['name' => ['Nama ini sudah dipakai kucing lain.']]
                );
            }

            $lockedCat->name = $name;
            $lockedCat->is_name_locked = true;
            $lockedCat->save();

            return $this->formatCat($lockedCat->loadMissing('territory'), $dex);
        });
    }

    // ------------------------------------------------------------------
    // Katalog, zona, profil
    // ------------------------------------------------------------------

    /**
     * Daftar kucing kampus (ON_CAMPUS) beserta progres user.
     */
    public function getUserCatalog(User $user): array
    {
        return $this->buildCatalog($user)->values()->all();
    }

    /**
     * Ringkasan 4 zona: jumlah kucing & indikator masih ada kucing yang belum disapa hari ini.
     */
    public function getZonesSummary(User $user): array
    {
        $catalog = $this->buildCatalog($user);
        $territories = Territory::all()->keyBy('code');

        $zones = [];
        foreach (self::ZONES as $code => $zone) {
            $cats = $catalog->where('zone_code', $code);

            $territoryId = null;
            foreach ($zone['territory_codes'] as $tCode) {
                if ($territories->has($tCode)) {
                    $territoryId = $territories[$tCode]->id;
                    break;
                }
            }

            $zones[] = [
                'id' => $territoryId,
                'code' => $code,
                'name' => $zone['name'],
                'short_label' => $zone['short_label'],
                'description' => $zone['description'],
                'landmark' => $zone['landmark'],
                'active_cats_count' => $cats->count(),
                'collected_cats_count' => $cats->where('is_collected', true)->count(),
                'has_unvisited_cat_today' => $cats->contains(fn ($c) => $c['can_scan_today']),
                'resident_cat_ids' => $cats->pluck('id')->values()->all(),
            ];
        }

        return $zones;
    }

    public function getUserProfileStats(User $user): array
    {
        $catalog = $this->buildCatalog($user);
        $collected = $catalog->where('is_collected', true);

        $totalEncounters = (int) $catalog->sum('encounter_count');
        $totalCollected = $collected->count();
        $totalCats = $catalog->count();
        $maxEncounter = (int) ($catalog->max('encounter_count') ?? 0);
        $nameLockedCount = $collected->where('is_name_locked', true)->count();

        $bestBuddy = $collected
            ->sort(function ($a, $b) {
                return [$b['encounter_count'], $b['last_scanned_at'] ?? ''] <=> [$a['encounter_count'], $a['last_scanned_at'] ?? ''];
            })
            ->first();

        $favorite = $catalog->firstWhere('is_favorite_avatar', true);

        return [
            'total_cats_collected' => $totalCollected,
            'total_catalog_cats' => $totalCats,
            'total_encounters' => $totalEncounters,
            'title' => $this->resolveTitle($totalEncounters),
            'best_buddy' => $bestBuddy,
            'medals' => $this->buildMedals($totalCollected, $totalCats, $maxEncounter, $totalEncounters, $nameLockedCount),
            'favorite_avatar' => $favorite,
        ];
    }

    /**
     * Pilih kucing yang sudah dikoleksi sebagai avatar favorit (hanya satu per user).
     * Catatan: ID kucing adalah ULID (string), bukan integer.
     */
    public function setFavoriteAvatar(User $user, string $catId): array
    {
        return DB::transaction(function () use ($user, $catId) {
            $dex = UserCatDex::where('user_id', $user->id)
                ->where('cat_id', $catId)
                ->where('encounter_count', '>', 0)
                ->lockForUpdate()
                ->first();

            if (!$dex) {
                throw new CatHunterException(
                    'Kucing ini belum masuk koleksimu. Sapa dulu sebelum dijadikan avatar.',
                    'CAT_NOT_COLLECTED',
                    422,
                    ['cat_id' => ['Kucing belum dikoleksi.']]
                );
            }

            UserCatDex::where('user_id', $user->id)->where('is_favorite_avatar', true)
                ->update(['is_favorite_avatar' => false]);

            $dex->is_favorite_avatar = true;
            $dex->save();

            return $this->formatCat(Cat::with('territory')->findOrFail($catId), $dex);
        });
    }

    // ------------------------------------------------------------------
    // Helper publik untuk controller
    // ------------------------------------------------------------------

    public function assertCatAvailable(Cat $cat): void
    {
        if (!$this->isEncounterable($cat)) {
            throw new CatHunterException(
                'Kucing ini sedang tidak berada di kampus atau sedang dalam perawatan sakit/terluka.',
                'CAT_NOT_AVAILABLE',
                422
            );
        }
    }

    public function formatCat(Cat $cat, ?UserCatDex $dex = null): array
    {
        $count = $dex?->encounter_count ?? 0;
        $zone = $this->zoneFor($cat);
        $state = $this->cooldownState($dex?->last_scanned_at);
        $isCollected = $count > 0;

        return [
            'id' => $cat->id,
            'unique_code' => $cat->unique_code,
            'qr_hash' => $cat->unique_code,
            'name' => $cat->name,
            'variant' => $this->variantFor($cat),
            'zone' => $zone ? self::ZONES[$zone]['name'] : null,
            'zone_code' => $zone,
            'location_detail' => $cat->description,
            // Data fisik & medis: read-only dari tabel `cats`.
            'health_status' => $cat->health_status,
            'health_label' => $this->healthLabel($cat),
            'is_sterilized' => $cat->is_sterilized,
            'is_vaccinated' => $cat->is_vaccinated,
            'personality' => $cat->temperament,
            'special_needs' => $cat->special_needs,
            'gender' => $cat->gender,
            'age_group' => $cat->age_group,
            'primary_color' => $cat->primary_color,
            'fur_pattern' => $cat->fur_pattern,
            'quote' => $cat->quote ?: self::DEFAULT_QUOTE,
            // Progres user.
            'encounter_count' => $count,
            'last_scanned_at' => $this->iso($dex?->last_scanned_at),
            'is_collected' => $isCollected,
            'is_currently_in_cafe' => $isCollected,
            'is_favorite_avatar' => (bool) ($dex?->is_favorite_avatar),
            'can_scan_today' => $this->isEncounterable($cat) && $state['can_scan_today'],
            'next_available_at' => $state['can_scan_today'] ? null : $state['next_available_at'],
            'is_name_locked' => (bool) $cat->is_name_locked,
            'can_rename' => $this->canRename($count, $cat),
            'rename_required_encounters' => self::RENAME_REQUIRED_ENCOUNTERS,
            'cafe_pose' => self::CAFE_POSES[crc32((string) $cat->id) % count(self::CAFE_POSES)],
        ];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function buildCatalog(User $user): Collection
    {
        $dexByCat = UserCatDex::where('user_id', $user->id)->get()->keyBy('cat_id');

        return Cat::with('territory')
            ->where('presence_status', 'ON_CAMPUS')
            ->orderBy('unique_code')
            ->get()
            ->map(fn (Cat $cat) => $this->formatCat($cat, $dexByCat->get($cat->id)));
    }

    private function ensureDexRow(User $user, Cat $cat): void
    {
        try {
            UserCatDex::firstOrCreate(
                ['user_id' => $user->id, 'cat_id' => $cat->id],
                ['encounter_count' => 0]
            );
        } catch (UniqueConstraintViolationException) {
            // Request paralel sudah membuatnya; lanjut dengan lock.
        }
    }

    private function cooldownState(?CarbonInterface $lastScannedAt): array
    {
        $startOfToday = now(self::TIMEZONE)->startOfDay();
        $scannedToday = $lastScannedAt !== null && $lastScannedAt->greaterThanOrEqualTo($startOfToday);

        return [
            'can_scan_today' => !$scannedToday,
            'last_scanned_at' => $this->iso($lastScannedAt),
            'next_available_at' => $startOfToday->copy()->addDay()->toIso8601String(),
        ];
    }

    /** Selaras dengan hub aksi CatController::actions (game.encounter). */
    private function isEncounterable(Cat $cat): bool
    {
        return $cat->presence_status === 'ON_CAMPUS'
            && !in_array($cat->health_status, ['SICK', 'INJURED'], true);
    }

    private function canRename(int $encounterCount, Cat $cat): bool
    {
        return $encounterCount >= self::RENAME_REQUIRED_ENCOUNTERS && !$cat->is_name_locked;
    }

    private function iso(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(self::TIMEZONE)->toIso8601String();
    }

    private function zoneFor(Cat $cat): ?string
    {
        $code = $cat->territory?->code;
        if (!$code) {
            return null;
        }

        foreach (self::ZONES as $zoneCode => $zone) {
            if (in_array($code, $zone['territory_codes'], true)) {
                return $zoneCode;
            }
        }

        return null;
    }

    private function variantFor(Cat $cat): string
    {
        $text = mb_strtolower(trim(($cat->primary_color ?? '') . ' ' . ($cat->color_pattern ?? '')));

        foreach ([
            'oren' => ['oren', 'oranye', 'orange', 'jingga', 'ginger'],
            'hitam' => ['hitam', 'black', 'tuxedo'],
            'putih' => ['putih', 'white', 'calico'],
        ] as $variant => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $variant;
                }
            }
        }

        return 'oren';
    }

    private function healthLabel(Cat $cat): string
    {
        $health = match ($cat->health_status) {
            'HEALTHY' => 'Sehat',
            'SICK' => 'Sedang Sakit',
            'INJURED' => 'Sedang Terluka',
            'UNDER_CARE' => 'Dalam Perawatan',
            default => 'Dalam Pantauan Pengelola',
        };

        if ($cat->health_status === 'HEALTHY' && $cat->is_sterilized) {
            $health .= ' & Steril';
        }

        return $health . ' (Data Pengelola)';
    }

    private function resolveTitle(int $totalEncounters): string
    {
        return match (true) {
            $totalEncounters >= 15 => self::TITLE_MASTER,
            $totalEncounters >= 5 => self::TITLE_FRIEND,
            default => self::TITLE_NEWCOMER,
        };
    }

    private function buildMedals(int $collected, int $totalCats, int $maxEncounter, int $totalEncounters, int $nameLocked): array
    {
        $defs = [
            ['medal_bestie', 'Sahabat Sejati', 'Capai 5x pertemuan dengan 1 kucing kampus untuk membuka hak memberi nama permanen.', $maxEncounter, 5],
            ['medal_explorer', 'Penjelajah Kampus', 'Temui minimal 3 kucing kampus berbeda di lingkungan Telkom University.', $collected, 3],
            ['medal_pawang', 'Pawang Tel-U', 'Capai total 15 pertemuan sah bersama kucing-kucing kampus.', $totalEncounters, 15],
            ['medal_namer', 'Penjaga Nama', 'Beri nama permanen unik kepada 4 ekor kucing kampus setelah 5x pertemuan.', $nameLocked, 4],
            ['medal_collector', 'Kolektor Penuh', 'Temui seluruh kucing kampus yang terdaftar di katalog Cat-Dex.', $collected, max($totalCats, 1)],
            ['medal_veteran', 'Sahabat Setia 30x', 'Kumpulkan total 30 pertemuan penuh kasih tanpa kontak paksa terhadap satwa.', $totalEncounters, 30],
        ];

        return array_map(function ($d) {
            [$id, $title, $description, $current, $target] = $d;
            $unlocked = $current >= $target;

            return [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'current' => min($current, $target),
                'target' => $target,
                'is_unlocked' => $unlocked,
                'progress_label' => $unlocked ? 'Tercapai' : min($current, $target) . '/' . $target,
            ];
        }, $defs);
    }
}
