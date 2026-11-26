<?php

namespace App\Modules\Cat\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cat extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    public const GENDERS = ['MALE', 'FEMALE', 'UNKNOWN'];
    public const AGE_GROUPS = ['KITTEN', 'ADULT', 'SENIOR', 'UNKNOWN'];
    public const PRESENCE_STATUSES = ['ON_CAMPUS', 'ADOPTED', 'MISSING', 'DECEASED'];
    public const HEALTH_STATUSES = ['HEALTHY', 'SICK', 'INJURED', 'UNDER_CARE'];
    public const ADOPTION_STATUSES = ['UNREVIEWED', 'READY', 'ADOPTED', 'RETURN_PENDING', 'RETURNED_REVIEW'];
    public const VERIFICATION_STATUSES = ['UNVERIFIED', 'VERIFIED'];

    protected $fillable = [
        'unique_code',
        'name',
        'is_name_locked',
        'gender',             // MALE, FEMALE, UNKNOWN
        'color_pattern',
        'primary_color',
        'fur_pattern',
        'age_group',          // KITTEN, ADULT, SENIOR, UNKNOWN
        'is_sterilized',
        'is_vaccinated',
        'vaccinated_at',
        'vaccination_notes',
        'description',
        'quote',
        'temperament',
        'special_needs',
        'traits',
        'territory_id',
        'primary_photo_media_id',
        'presence_status',    // ON_CAMPUS, ADOPTED, MISSING, DECEASED
        'health_status',        // HEALTHY, SICK, INJURED, UNDER_CARE
        'adoption_status',      // UNREVIEWED, READY, ADOPTED, RETURN_PENDING, RETURNED_REVIEW
        'verification_status',  // UNVERIFIED, VERIFIED
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_sterilized' => 'boolean',
        'is_name_locked' => 'boolean',
        'is_vaccinated' => 'boolean',
        'vaccinated_at' => 'date',
        'traits' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'territory_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CatPhoto::class, 'cat_id')->orderBy('sort_order');
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(CatQrToken::class, 'cat_id');
    }

    public function activeQrToken(): HasOne
    {
        return $this->hasOne(CatQrToken::class, 'cat_id')->where('status', 'ACTIVE');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(CatStatusLog::class, 'cat_id')->latest();
    }

    public function feedingSpots(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Modules\FeedingSpot\Models\FeedingSpot::class, 'feeding_spot_cats', 'cat_id', 'spot_id')
            ->withTimestamps();
    }
}
