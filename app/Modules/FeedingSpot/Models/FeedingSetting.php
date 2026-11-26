<?php

namespace App\Modules\FeedingSpot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FeedingSetting extends Model
{
    protected $table = 'feeding_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Default parameters per PRD Lampiran A.1, Bagian 8.4, & MEOW-21.
     */
    public const DEFAULTS = [
        'intent_window_minutes' => ['value' => '15', 'description' => 'Batas waktu konfirmasi intent setelah scan QR (menit)'],
        'finalize_window_minutes' => ['value' => '60', 'description' => 'Batas waktu finalisasi bukti setelah scan QR (menit)'],
        'cooldown_hours' => ['value' => '6', 'description' => 'Cooldown pemberian pakan pengguna yang sama di spot yang sama (jam)'],
        'random_sample_rate' => ['value' => '0.10', 'description' => 'Rasio sampel acak pengajuan yang wajib masuk review manual (0.00 - 1.00)'],
        'new_user_review_threshold' => ['value' => '2', 'description' => 'Jumlah aktivitas pertama pengguna baru yang wajib masuk antrean review'],
        'daily_reward_cap_count' => ['value' => '3', 'description' => 'Batas frekuensi pemberian pakan berpoin per pengguna per hari'],
        'target_14d_feedings' => ['value' => '28', 'description' => 'Target ideal pemberian makan per spot dalam 14 hari (2x/hari)'],
        'base_points' => ['value' => '5', 'description' => 'Poin dasar per aktivitas pemberian pakan terverifikasi'],
        'min_points' => ['value' => '5', 'description' => 'Batas minimum poin per aktivitas pemberian pakan'],
        'max_points' => ['value' => '20', 'description' => 'Batas maksimum poin per aktivitas pemberian pakan'],
        'max_frequency_bonus' => ['value' => '8', 'description' => 'Bonus maksimum kelangkaan frekuensi 14 hari'],
        'max_recency_bonus' => ['value' => '7', 'description' => 'Bonus maksimum selang waktu sejak pakan terakhir'],
        'recency_Reference_hours' => ['value' => '24', 'description' => 'Referensi jam untuk bonus recency maksimal'],
    ];

    /**
     * Metadata parameter yang dapat diubah admin lewat GET/PUT /admin/feeding/settings dan Panel Admin (MEOW-21).
     */
    public const EDITABLE_SPECS = [
        'intent_window_minutes' => [
            'label' => 'Batas Waktu Intent (menit)',
            'type' => 'integer',
            'min' => 1,
            'max' => 120,
            'step' => 1,
        ],
        'finalize_window_minutes' => [
            'label' => 'Batas Waktu Finalisasi (menit)',
            'type' => 'integer',
            'min' => 5,
            'max' => 360,
            'step' => 1,
        ],
        'cooldown_hours' => [
            'label' => 'Cooldown per Spot (jam)',
            'type' => 'integer',
            'min' => 1,
            'max' => 72,
            'step' => 1,
        ],
        'random_sample_rate' => [
            'label' => 'Rasio Sampling Acak (0 - 1)',
            'type' => 'numeric',
            'min' => 0,
            'max' => 1,
            'step' => 0.01,
        ],
        'new_user_review_threshold' => [
            'label' => 'Wajib Review Pengguna Baru (kali)',
            'type' => 'integer',
            'min' => 0,
            'max' => 20,
            'step' => 1,
        ],
        'daily_reward_cap_count' => [
            'label' => 'Batas Reward Harian (kali/hari)',
            'type' => 'integer',
            'min' => 1,
            'max' => 20,
            'step' => 1,
        ],
        'target_14d_feedings' => [
            'label' => 'Target 14 Hari per Spot (aktivitas)',
            'type' => 'integer',
            'min' => 1,
            'max' => 200,
            'step' => 1,
        ],
        'base_points' => [
            'label' => 'Poin Dasar (Base Poin)',
            'type' => 'integer',
            'min' => 1,
            'max' => 100,
            'step' => 1,
        ],
        'min_points' => [
            'label' => 'Poin Minimum',
            'type' => 'integer',
            'min' => 0,
            'max' => 100,
            'step' => 1,
        ],
        'max_points' => [
            'label' => 'Poin Maksimum',
            'type' => 'integer',
            'min' => 1,
            'max' => 200,
            'step' => 1,
        ],
    ];

    /**
     * Sinkronisasi kunci alias yang dibaca oleh FeedingRewardService agar perubahan dari admin langsung efektif.
     */
    private const ALIAS_MAP = [
        'base_points' => 'reward_base_p0',
        'min_points' => 'reward_min_points',
        'max_points' => 'reward_max_points',
        'target_14d_feedings' => 'target_feedings_14d',
        'daily_reward_cap_count' => 'daily_reward_cap',
    ];

    public static function ensureDefaults(): void
    {
        foreach (self::DEFAULTS as $key => $meta) {
            self::firstOrCreate(
                ['key' => $key],
                ['value' => $meta]
            );
        }
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = self::find($key);
        if ($row && is_array($row->value) && array_key_exists('value', $row->value)) {
            return $row->value['value'];
        }
        if ($row && !is_array($row->value)) {
            return $row->value;
        }

        if (isset(self::DEFAULTS[$key])) {
            return self::DEFAULTS[$key]['value'];
        }

        return $default;
    }

    public static function getFloat(string $key, float $default = 0.0): float
    {
        return (float) self::getValue($key, $default);
    }

    /**
     * Daftar seluruh pengaturan editable beserta nilai terkini dan deskripsinya (MEOW-21).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allEditable(): array
    {
        self::ensureDefaults();

        $result = [];
        foreach (self::EDITABLE_SPECS as $key => $spec) {
            $rawValue = self::getValue($key, self::DEFAULTS[$key]['value'] ?? '0');
            $typedValue = $spec['type'] === 'integer' ? (int) round((float) $rawValue) : (float) $rawValue;

            $result[$key] = [
                'key' => $key,
                'label' => $spec['label'],
                'value' => $typedValue,
                'description' => self::DEFAULTS[$key]['description'] ?? '',
                'type' => $spec['type'],
                'min' => $spec['min'],
                'max' => $spec['max'],
                'step' => $spec['step'],
            ];
        }

        return $result;
    }

    /**
     * Perbarui beberapa pengaturan feeding_settings dalam satu transaksi dan catat ke audit_logs.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array<string, mixed>>
     */
    public static function updateMany(array $values, ?User $actor = null): array
    {
        self::ensureDefaults();

        return DB::transaction(function () use ($values, $actor) {
            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach (self::EDITABLE_SPECS as $key => $spec) {
                if (!array_key_exists($key, $values)) {
                    continue;
                }

                $currentRaw = self::getValue($key, self::DEFAULTS[$key]['value'] ?? '0');
                $currentVal = $spec['type'] === 'integer' ? (int) round((float) $currentRaw) : (float) $currentRaw;
                $nextVal = $spec['type'] === 'integer' ? (int) $values[$key] : round((float) $values[$key], 4);

                $storedString = $spec['type'] === 'integer'
                    ? (string) $nextVal
                    : rtrim(rtrim(number_format($nextVal, 4, '.', ''), '0'), '.');
                if ($storedString === '') {
                    $storedString = '0';
                }

                $description = self::DEFAULTS[$key]['description'] ?? $spec['label'];

                self::updateOrCreate(
                    ['key' => $key],
                    ['value' => ['value' => $storedString, 'description' => $description]]
                );

                if (isset(self::ALIAS_MAP[$key])) {
                    self::updateOrCreate(
                        ['key' => self::ALIAS_MAP[$key]],
                        ['value' => ['value' => $storedString, 'description' => $description]]
                    );
                }

                if ($currentVal !== $nextVal) {
                    $changedFields[] = $key;
                    $oldValues[$key] = $currentVal;
                    $newValues[$key] = $nextVal;
                }
            }

            if (!empty($changedFields) && Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->insert([
                    'id' => (string) Str::ulid(),
                    'actor_id' => $actor?->id,
                    'action' => 'FEEDING_SETTINGS_UPDATED',
                    'subject_type' => 'feeding_settings',
                    'subject_id' => 'feeding_settings',
                    'result' => 'SUCCESS',
                    'reason' => json_encode([
                        'old' => $oldValues,
                        'new' => $newValues,
                        'changed_fields' => $changedFields,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return self::allEditable();
        });
    }
}
