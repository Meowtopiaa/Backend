<?php

namespace App\Modules\Cat\Services;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Models\CatPhoto;
use App\Modules\Cat\Models\CatQrToken;
use App\Modules\Core\Services\CatStatusService;
use App\Modules\Core\Services\MediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Layanan manajemen CRUD Katalog Kucing (Admin / Data Curator).
 * - Satu tabel `cats` sebagai single source of truth.
 * - Perubahan status wajib melalui `CatStatusService::change()`.
 * - QR Code `MTP1.C.<token>` dibuat otomatis saat kucing baru ditambahkan.
 * - Audit log tidak pernah menyimpan raw QR token.
 */
class CatManagementService
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
        private readonly CatStatusService $catStatusService,
        private readonly MediaService $mediaService,
        private readonly CatCatalogPresenter $presenter,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateAdminCats(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Cat::query()->with(['territory', 'photos', 'activeQrToken']);

        if (!empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        foreach (['presence_status', 'adoption_status', 'health_status', 'gender', 'age_group', 'verification_status'] as $enumField) {
            if (!empty($filters[$enumField])) {
                $query->where($enumField, strtoupper((string) $filters[$enumField]));
            }
        }

        if (!empty($filters['territory_id'])) {
            $query->where('territory_id', (string) $filters['territory_id']);
        }

        if (isset($filters['is_sterilized']) && $filters['is_sterilized'] !== '' && $filters['is_sterilized'] !== null) {
            $query->where('is_sterilized', filter_var($filters['is_sterilized'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['is_vaccinated']) && $filters['is_vaccinated'] !== '' && $filters['is_vaccinated'] !== null) {
            $query->where('is_vaccinated', filter_var($filters['is_vaccinated'], FILTER_VALIDATE_BOOLEAN));
        }

        $search = trim((string) ($filters['search'] ?? $filters['q'] ?? ''));
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

        $perPage = max(1, min(100, $perPage));
        $paginator = $query->orderByDesc('created_at')->orderBy('name')->paginate($perPage);
        $paginator->setCollection(collect($this->presenter->mapAdminCollection($paginator->getCollection())));

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminCatDetail(Cat $cat): array
    {
        $cat->load(['territory', 'photos', 'activeQrToken', 'statusLogs']);
        $rarities = $this->presenter->loadGameRarities([$cat->id]);

        return $this->presenter->toAdminArray($cat, $rarities[$cat->id] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createCat(array $data, User $actor, ?UploadedFile $photo = null): Cat
    {
        return DB::transaction(function () use ($data, $actor, $photo) {
            $uniqueCode = !empty($data['unique_code'])
                ? strtoupper(trim((string) $data['unique_code']))
                : $this->generateUniqueCode();

            $primaryColor = $this->nullableTrim($data['primary_color'] ?? null);
            $furPattern = $this->nullableTrim($data['fur_pattern'] ?? null);
            $colorPattern = $this->resolveColorPattern(
                $this->nullableTrim($data['color_pattern'] ?? null),
                $primaryColor,
                $furPattern
            );

            $requestedPresence = strtoupper((string) ($data['presence_status'] ?? 'ON_CAMPUS'));
            $requestedHealth = strtoupper((string) ($data['health_status'] ?? 'HEALTHY'));
            $requestedAdoption = strtoupper((string) ($data['adoption_status'] ?? 'UNREVIEWED'));

            $cat = Cat::create([
                'unique_code' => $uniqueCode,
                'name' => trim((string) $data['name']),
                'gender' => strtoupper((string) ($data['gender'] ?? 'UNKNOWN')),
                'primary_color' => $primaryColor,
                'fur_pattern' => $furPattern,
                'color_pattern' => $colorPattern,
                'age_group' => strtoupper((string) ($data['age_group'] ?? 'UNKNOWN')),
                'is_sterilized' => array_key_exists('is_sterilized', $data) && $data['is_sterilized'] !== null
                    ? (bool) $data['is_sterilized']
                    : null,
                'is_vaccinated' => array_key_exists('is_vaccinated', $data) && $data['is_vaccinated'] !== null
                    ? (bool) $data['is_vaccinated']
                    : null,
                'vaccinated_at' => $data['vaccinated_at'] ?? null,
                'vaccination_notes' => $this->nullableTrim($data['vaccination_notes'] ?? null),
                'description' => $this->nullableTrim($data['description'] ?? null),
                'temperament' => $this->nullableTrim($data['temperament'] ?? null),
                'special_needs' => $this->nullableTrim($data['special_needs'] ?? null),
                'traits' => $this->sanitizeTraits($data['traits'] ?? null),
                'territory_id' => $data['territory_id'] ?? null,
                'primary_photo_media_id' => $data['primary_photo_media_id'] ?? null,
                'presence_status' => 'ON_CAMPUS',
                'health_status' => 'HEALTHY',
                'adoption_status' => 'UNREVIEWED',
                'verification_status' => strtoupper((string) ($data['verification_status'] ?? 'VERIFIED')),
                'first_seen_at' => now(),
            ]);

            // Transisi status non-default tetap melewati CatStatusService::change() (Aturan Keras #2)
            $reason = $this->nullableTrim($data['status_reason'] ?? null) ?? 'Inisialisasi data kucing baru oleh admin';
            if ($requestedPresence !== 'ON_CAMPUS') {
                $cat = $this->catStatusService->change($cat, 'presence_status', $requestedPresence, $actor, 'Cat', $reason, 'cat', $cat->id);
            }
            if ($requestedHealth !== 'HEALTHY') {
                $cat = $this->catStatusService->change($cat, 'health_status', $requestedHealth, $actor, 'Cat', $reason, 'cat', $cat->id);
            }
            if ($requestedAdoption !== 'UNREVIEWED') {
                $cat = $this->catStatusService->change($cat, 'adoption_status', $requestedAdoption, $actor, 'Cat', $reason, 'cat', $cat->id);
            }

            // Otomatis terbitkan 1 token QR aktif (MTP1.C.<token>) untuk kalung kucing
            CatQrToken::create([
                'cat_id' => $cat->id,
                'token' => $this->qrCodeService->generateToken(),
                'status' => 'ACTIVE',
                'issued_by' => $actor->id,
                'issued_at' => now(),
            ]);

            if ($photo !== null) {
                $this->attachUploadedPhoto($cat, $photo, $actor, $data['photo_angle'] ?? 'front', true);
            } elseif (!empty($data['primary_photo_media_id'])) {
                CatPhoto::firstOrCreate(
                    ['cat_id' => $cat->id, 'media_id' => $data['primary_photo_media_id']],
                    ['angle' => $data['photo_angle'] ?? 'front', 'sort_order' => 0]
                );
            }

            $this->recordAudit($actor, 'CAT_CREATED', $cat->id, 'Data kucing baru dan QR kalung otomatis dibuat.');

            return $cat->fresh(['territory', 'photos', 'activeQrToken', 'statusLogs']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCat(Cat $cat, array $data, User $actor, ?UploadedFile $photo = null): Cat
    {
        return DB::transaction(function () use ($cat, $data, $actor, $photo) {
            $updatable = [];

            if (array_key_exists('unique_code', $data) && $data['unique_code'] !== null) {
                $updatable['unique_code'] = strtoupper(trim((string) $data['unique_code']));
            }
            if (array_key_exists('name', $data)) {
                $updatable['name'] = trim((string) $data['name']);
            }
            if (array_key_exists('gender', $data) && $data['gender'] !== null) {
                $updatable['gender'] = strtoupper((string) $data['gender']);
            }
            if (array_key_exists('age_group', $data) && $data['age_group'] !== null) {
                $updatable['age_group'] = strtoupper((string) $data['age_group']);
            }
            if (array_key_exists('is_sterilized', $data)) {
                $updatable['is_sterilized'] = $data['is_sterilized'] === null ? null : (bool) $data['is_sterilized'];
            }
            if (array_key_exists('is_vaccinated', $data)) {
                $updatable['is_vaccinated'] = $data['is_vaccinated'] === null ? null : (bool) $data['is_vaccinated'];
            }
            if (array_key_exists('vaccinated_at', $data)) {
                $updatable['vaccinated_at'] = $data['vaccinated_at'];
            }
            if (array_key_exists('vaccination_notes', $data)) {
                $updatable['vaccination_notes'] = $this->nullableTrim($data['vaccination_notes']);
            }
            if (array_key_exists('description', $data)) {
                $updatable['description'] = $this->nullableTrim($data['description']);
            }
            if (array_key_exists('temperament', $data)) {
                $updatable['temperament'] = $this->nullableTrim($data['temperament']);
            }
            if (array_key_exists('special_needs', $data)) {
                $updatable['special_needs'] = $this->nullableTrim($data['special_needs']);
            }
            if (array_key_exists('traits', $data)) {
                $updatable['traits'] = $this->sanitizeTraits($data['traits']);
            }
            if (array_key_exists('territory_id', $data)) {
                $updatable['territory_id'] = $data['territory_id'];
            }
            if (array_key_exists('primary_photo_media_id', $data)) {
                $updatable['primary_photo_media_id'] = $data['primary_photo_media_id'];
            }
            if (array_key_exists('verification_status', $data) && $data['verification_status'] !== null) {
                $updatable['verification_status'] = strtoupper((string) $data['verification_status']);
            }

            $colorFieldsTouched = array_key_exists('primary_color', $data)
                || array_key_exists('fur_pattern', $data)
                || array_key_exists('color_pattern', $data);

            if ($colorFieldsTouched) {
                $primaryColor = array_key_exists('primary_color', $data)
                    ? $this->nullableTrim($data['primary_color'])
                    : $cat->primary_color;
                $furPattern = array_key_exists('fur_pattern', $data)
                    ? $this->nullableTrim($data['fur_pattern'])
                    : $cat->fur_pattern;
                $explicitColorPattern = array_key_exists('color_pattern', $data)
                    ? $this->nullableTrim($data['color_pattern'])
                    : null;

                $updatable['primary_color'] = $primaryColor;
                $updatable['fur_pattern'] = $furPattern;
                $updatable['color_pattern'] = $this->resolveColorPattern($explicitColorPattern, $primaryColor, $furPattern);
            }

            if (!empty($updatable)) {
                $cat->fill($updatable);
                $cat->save();
            }

            // Perubahan 3 dimensi status WAJIB melalui CatStatusService::change() (Aturan Keras #2)
            $reason = $this->nullableTrim($data['status_reason'] ?? null) ?? 'Pembaruan profil katalog oleh admin';
            foreach (['presence_status', 'health_status', 'adoption_status'] as $dimension) {
                if (array_key_exists($dimension, $data) && $data[$dimension] !== null) {
                    $newValue = strtoupper((string) $data[$dimension]);
                    if ($newValue !== $cat->{$dimension}) {
                        $cat = $this->catStatusService->change($cat, $dimension, $newValue, $actor, 'Cat', $reason, 'cat', $cat->id);
                    }
                }
            }

            if ($photo !== null) {
                $this->attachUploadedPhoto($cat, $photo, $actor, $data['photo_angle'] ?? 'front', true);
            }

            $this->recordAudit($actor, 'CAT_UPDATED', $cat->id, 'Profil kucing diperbarui oleh admin.');

            return $cat->fresh(['territory', 'photos', 'activeQrToken', 'statusLogs']);
        });
    }

    public function attachUploadedPhoto(
        Cat $cat,
        UploadedFile $file,
        User $actor,
        string $angle = 'front',
        bool $makePrimary = false
    ): CatPhoto {
        $stored = $this->mediaService->store($file, 'cat_photo', $actor);
        $nextOrder = (int) CatPhoto::where('cat_id', $cat->id)->max('sort_order') + 1;

        $catPhoto = CatPhoto::create([
            'cat_id' => $cat->id,
            'media_id' => $stored['id'],
            'angle' => $angle,
            'sort_order' => $makePrimary ? 0 : $nextOrder,
        ]);

        if ($makePrimary || $cat->primary_photo_media_id === null) {
            $cat->primary_photo_media_id = $stored['id'];
            $cat->save();
        }

        return $catPhoto;
    }

    public function softDeleteCat(Cat $cat, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($cat, $actor, $reason) {
            $cat->delete();
            $this->recordAudit(
                $actor,
                'CAT_SOFT_DELETED',
                $cat->id,
                $this->nullableTrim($reason) ?? 'Data kucing dinonaktifkan (soft delete) tanpa menghapus riwayat.'
            );
        });
    }

    public function restoreCat(Cat $cat, User $actor): Cat
    {
        return DB::transaction(function () use ($cat, $actor) {
            $cat->restore();
            $this->recordAudit($actor, 'CAT_RESTORED', $cat->id, 'Data kucing diaktifkan kembali.');

            return $cat->fresh(['territory', 'photos', 'activeQrToken', 'statusLogs']);
        });
    }

    public function generateUniqueCode(): string
    {
        $existingCodes = Cat::withTrashed()->pluck('unique_code')->all();
        $maxNum = 0;

        foreach ($existingCodes as $code) {
            if (preg_match('/^CAT(\d+)$/i', (string) $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        do {
            $maxNum++;
            $candidate = 'CAT' . str_pad((string) $maxNum, 3, '0', STR_PAD_LEFT);
        } while (Cat::withTrashed()->where('unique_code', $candidate)->exists());

        return $candidate;
    }

    private function resolveColorPattern(?string $explicit, ?string $primaryColor, ?string $furPattern): ?string
    {
        if ($explicit !== null && $explicit !== '') {
            return $explicit;
        }

        $combined = trim(($primaryColor ?? '') . ' ' . ($furPattern ?? ''));

        return $combined !== '' ? $combined : null;
    }

    /**
     * @param  mixed  $traits
     * @return array<string, mixed>|null
     */
    private function sanitizeTraits(mixed $traits): ?array
    {
        if (!is_array($traits)) {
            return null;
        }

        $clean = [];
        foreach ($traits as $key => $value) {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $clean[(string) $key] = $trimmed;
                }
            } elseif ($value !== null) {
                $clean[(string) $key] = $value;
            }
        }

        return empty($clean) ? null : $clean;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function recordAudit(User $actor, string $action, string $catId, string $reason): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(),
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'cat',
            'subject_id' => $catId,
            'result' => 'SUCCESS',
            'reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
