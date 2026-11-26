<?php

namespace App\Modules\IncidentReport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat status tiket (incident_status_logs). Tambah-saja; tidak dihapus saat status berubah.
 */
class IncidentStatusLog extends Model
{
    use HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['incident_id', 'from_status', 'to_status', 'actor_id', 'note'];

    public function incident()
    {
        return $this->belongsTo(IncidentReport::class, 'incident_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
