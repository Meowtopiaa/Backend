<?php

namespace App\Modules\IncidentReport\Models;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tiket insiden fisik di kampus (PRD 5.5, 6.6, 7.4). Pemilik: Optimind.
 */
class IncidentReport extends Model
{
    use HasFactory, HasUlids;

    protected $keyType = 'string';
    public $incrementing = false;

    // Status (PRD 5.5)
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_WAITING_ASSIGNMENT = 'WAITING_ASSIGNMENT';
    public const STATUS_ASSIGNED = 'ASSIGNED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_RESOLVED = 'RESOLVED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_DUPLICATE = 'DUPLICATE';

    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_WAITING_ASSIGNMENT,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
        self::STATUS_REJECTED,
        self::STATUS_DUPLICATE,
    ];

    public const FINAL_STATUSES = [self::STATUS_CLOSED, self::STATUS_REJECTED, self::STATUS_DUPLICATE];

    // Kategori (EIR-02)
    public const CATEGORIES = ['SICK', 'INJURED', 'MISSING', 'FOUND', 'AGGRESSIVE', 'DECEASED', 'NEW_CAT', 'DAMAGED_QR', 'OTHER'];
    // Kategori yang boleh dipakai untuk laporan baru (keputusan tim Optimind).
    // MISSING, FOUND, NEW_CAT, DAMAGED_QR tetap dikenal untuk data lama dan filter admin.
    public const REPORTABLE_CATEGORIES = ['SICK', 'INJURED', 'AGGRESSIVE', 'DECEASED', 'OTHER'];
    public const HEALTH_CATEGORIES = ['SICK', 'INJURED'];
    public const PRESENCE_CATEGORIES = ['MISSING', 'DECEASED'];

    public const URGENCIES = ['LOW', 'MEDIUM', 'HIGH'];

    // Asal laporan (EIR-10)
    public const SOURCE_DIRECT = 'DIRECT';
    public const SOURCE_ADOPTION = 'ADOPTION';
    public const SOURCES = ['DIRECT', 'GAME', 'PROFILE', 'ADOPTION'];

    // Status notifikasi (PRD 8.7, FIX-09)
    public const NOTIFY_NOT_REQUESTED = 'NOT_REQUESTED';
    public const NOTIFY_PENDING = 'PENDING';
    public const NOTIFY_SENT = 'SENT';
    public const NOTIFY_FAILED = 'FAILED';

    protected $fillable = [
        'ticket_code',
        'reporter_id',
        'cat_id',
        'category',
        'urgency',
        'description',
        'location_lat',
        'location_lng',
        'location_accuracy_m',
        'location_note',
        'territory_id',
        'source',
        'source_ref',
        'status',
        'assigned_to',
        'assigned_at',
        'resolved_at',
        'closed_at',
        'resolution_note',
        'notification_status',
        'verified_at',
        'verified_by',
        'cat_health_applied',
        'possible_duplicate_of',
        'duplicate_of',
    ];

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
        'location_accuracy_m' => 'float',
        'assigned_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function cat()
    {
        return $this->belongsTo(Cat::class, 'cat_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusLogs()
    {
        return $this->hasMany(IncidentStatusLog::class, 'incident_id')->orderBy('created_at')->orderBy('id');
    }

    public function assignments()
    {
        return $this->hasMany(IncidentAssignment::class, 'incident_id');
    }

    public function activeAssignment()
    {
        return $this->hasOne(IncidentAssignment::class, 'incident_id')
            ->whereIn('status', IncidentAssignment::ACTIVE_STATUSES);
    }

    public function media()
    {
        return $this->hasMany(IncidentMedia::class, 'incident_id');
    }
}
