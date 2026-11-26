<?php

namespace App\Modules\CatHunter\Models;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log histori pertemuan (satu baris per sesi mengelus yang selesai).
 * Dipakai sebagai pemantauan keaktifan populasi kucing.
 */
class CatEncounter extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'cat_hunter_encounters';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'cat_id',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cat(): BelongsTo
    {
        return $this->belongsTo(Cat::class);
    }
}
