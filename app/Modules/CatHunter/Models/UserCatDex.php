<?php

namespace App\Modules\CatHunter\Models;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCatDex extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'cat_hunter_user_cat_dex';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'cat_id',
        'encounter_count',
        'last_scanned_at',
        'is_favorite_avatar',
    ];

    protected $casts = [
        'encounter_count' => 'integer',
        'last_scanned_at' => 'datetime',
        'is_favorite_avatar' => 'boolean',
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
