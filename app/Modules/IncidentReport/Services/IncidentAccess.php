<?php

namespace App\Modules\IncidentReport\Services;

use App\Models\User;
use App\Modules\IncidentReport\Models\CaretakerProfile;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Models\IncidentReport;

/**
 * Policy akses insiden (FIX-02, EIR-05, EIR-09, IT-11).
 *
 * Izin hanya lewat permission Spatie dan hubungan objek, tidak membaca string role:
 * - Admin antrean: incident.view + incident.dispatch. (Role caretaker di seeder hanya
 *   punya incident.view, jadi relawan tidak dapat membuka antrean semua laporan.)
 * - Pelapor: hanya tiket miliknya.
 * - Relawan: hanya tiket yang ditawarkan atau ditugaskan kepadanya.
 */
class IncidentAccess
{
    public const VIEW_ADMIN = 'admin';
    public const VIEW_CARETAKER = 'caretaker';
    public const VIEW_REPORTER = 'reporter';

    /** Assignment yang membuat relawan berhak melihat tiket. */
    public const CARETAKER_VISIBLE_STATUSES = [
        IncidentAssignment::STATUS_OFFERED,
        IncidentAssignment::STATUS_ACCEPTED,
        IncidentAssignment::STATUS_DONE,
    ];

    public function isQueueAdmin(?User $user): bool
    {
        return $user !== null && $user->can('incident.view') && $user->can('incident.dispatch');
    }

    public function canDispatch(?User $user): bool
    {
        return $user !== null && $user->can('incident.dispatch');
    }

    public function canClose(?User $user): bool
    {
        return $user !== null && $user->can('incident.close');
    }

    public function isCaretaker(?User $user): bool
    {
        return $user !== null && CaretakerProfile::whereKey($user->id)->exists();
    }

    public function caretakerAssignment(User $user, IncidentReport $incident, array $statuses = self::CARETAKER_VISIBLE_STATUSES): ?IncidentAssignment
    {
        return IncidentAssignment::where('incident_id', $incident->id)
            ->where('caretaker_id', $user->id)
            ->whereIn('status', $statuses)
            ->latest('created_at')
            ->first();
    }

    /**
     * Tingkat tampilan yang berhak dilihat pengguna untuk tiket ini, atau null bila tidak berhak.
     */
    public function viewFor(?User $user, IncidentReport $incident): ?string
    {
        if ($user === null) {
            return null;
        }

        if ($this->isQueueAdmin($user)) {
            return self::VIEW_ADMIN;
        }

        if ($this->caretakerAssignment($user, $incident) !== null) {
            return self::VIEW_CARETAKER;
        }

        if ($incident->reporter_id === $user->id) {
            return self::VIEW_REPORTER;
        }

        return null;
    }
}
