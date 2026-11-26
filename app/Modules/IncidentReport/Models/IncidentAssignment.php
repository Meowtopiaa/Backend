<?php

namespace App\Modules\IncidentReport\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Penugasan relawan (incident_assignments). Unik parsial: satu OFFERED/ACCEPTED per tiket.
 */
class IncidentAssignment extends Model
{
    use HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_OFFERED = 'OFFERED';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_DECLINED = 'DECLINED';
    public const STATUS_DONE = 'DONE';
    // Dihentikan admin (pengalihan dengan override, lepas penugasan, atau tiket ditutup).
    // Tidak tampil di daftar tugas relawan.
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACTIVE_STATUSES = [self::STATUS_OFFERED, self::STATUS_ACCEPTED];

    // PRD 8.7: tanpa respons dalam 15 menit, tiket kembali ke WAITING_ASSIGNMENT.
    public const OFFER_TTL_MINUTES = 15;

    protected $fillable = [
        'incident_id',
        'caretaker_id',
        'assigned_by',
        'status',
        'responded_at',
        'offer_expires_at',
        'ended_reason',
        'decline_reason',
        'note',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'offer_expires_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isOfferExpired(): bool
    {
        return $this->status === self::STATUS_OFFERED
            && $this->offer_expires_at !== null
            && $this->offer_expires_at->isPast();
    }

    public function incident()
    {
        return $this->belongsTo(IncidentReport::class, 'incident_id');
    }

    public function caretaker()
    {
        return $this->belongsTo(User::class, 'caretaker_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
