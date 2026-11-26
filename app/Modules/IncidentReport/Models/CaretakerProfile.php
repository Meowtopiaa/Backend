<?php

namespace App\Modules\IncidentReport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Profil relawan penerima penugasan insiden (caretaker_profiles). Dibuat admin insiden.
 * Nomor telepon hanya untuk admin; tidak pernah tampil ke pelapor.
 */
class CaretakerProfile extends Model
{
    protected $primaryKey = 'user_id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'is_available',
        'phone',
        'territory_ids',
        'last_location_lat',
        'last_location_lng',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'territory_ids' => 'array',
        'last_location_lat' => 'float',
        'last_location_lng' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
