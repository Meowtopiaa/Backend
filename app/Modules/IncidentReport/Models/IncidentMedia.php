<?php

namespace App\Modules\IncidentReport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class IncidentMedia extends Model
{
    use HasUlids;

    protected $table = 'incident_media';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['incident_id', 'media_id'];

    public function incident()
    {
        return $this->belongsTo(IncidentReport::class, 'incident_id');
    }
}
