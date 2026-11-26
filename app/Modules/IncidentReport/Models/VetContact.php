<?php

namespace App\Modules\IncidentReport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Kontak dokter hewan atau klinik mitra (EIR-12). Data, bukan akun login. Tidak boleh diisi fiktif.
 */
class VetContact extends Model
{
    use HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['name', 'address', 'phone', 'whatsapp', 'latitude', 'longitude', 'open_hours', 'is_active'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
    ];
}
