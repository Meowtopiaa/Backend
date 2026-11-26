<?php

namespace App\Modules\IncidentReport\Services;

use App\Models\User;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\CaretakerProfile;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Models\IncidentReport;
use App\Modules\IncidentReport\Notifications\IncidentTaskOffered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Penugasan relawan (EIR-06, EIR-08, IT-10, IT-15, PRD 8.7).
 *
 * - Admin menetapkan satu penanggung jawab utama; tawaran berlaku 15 menit.
 * - Penolakan atau tanpa respons mengembalikan tiket ke WAITING_ASSIGNMENT.
 * - Tidak ada dua penanggung jawab aktif tanpa override admin yang dicatat
 *   (indeks unik parsial + penguncian baris).
 * - Status notifikasi mencerminkan kenyataan (FIX-09).
 */
class IncidentDispatchService
{
    public const ASSIGNABLE_STATUSES = [
        IncidentReport::STATUS_RECEIVED,
        IncidentReport::STATUS_WAITING_ASSIGNMENT,
        IncidentReport::STATUS_ASSIGNED,
        IncidentReport::STATUS_IN_PROGRESS,
    ];

    public function __construct(
        private readonly IncidentWorkflowService $workflow,
        private readonly IncidentAuditLogger $audit,
        private readonly IncidentPresenter $presenter,
    ) {}

    /**
     * POST /admin/incidents/{id}/assign
     */
    public function assign(User $admin, string $incidentId, string $caretakerId, bool $override = false, ?string $reason = null): IncidentAssignment
    {
        $this->expireStaleOffers();

        try {
            $assignment = DB::transaction(function () use ($admin, $incidentId, $caretakerId, $override, $reason) {
                $incident = IncidentReport::whereKey($incidentId)->lockForUpdate()->first();
                if (!$incident) {
                    throw IncidentDomainException::notFound();
                }

                if (!in_array($incident->status, self::ASSIGNABLE_STATUSES, true)) {
                    throw IncidentDomainException::conflict("Tiket berstatus {$incident->status} tidak dapat ditugaskan.");
                }

                if ($this->presenter->handledByAdminOnly($incident)) {
                    throw IncidentDomainException::conflict('Tiket dari kucing teradopsi ditangani admin, bukan dispatch relawan kampus.');
                }

                $profile = CaretakerProfile::whereKey($caretakerId)->first();
                if ($profile === null) {
                    throw IncidentDomainException::validation('Pengguna ini belum terdaftar sebagai relawan insiden.', [
                        'caretaker_id' => ['Pengguna ini belum terdaftar sebagai relawan insiden.'],
                    ]);
                }

                if (!$profile->is_available && !$override) {
                    throw IncidentDomainException::conflict('Relawan sedang tidak tersedia. Gunakan override dengan alasan bila tetap ingin menugaskan.');
                }

                $active = IncidentAssignment::where('incident_id', $incident->id)
                    ->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
                    ->lockForUpdate()
                    ->first();

                if ($active !== null) {
                    if ($active->caretaker_id === $caretakerId) {
                        return $active; // Penugasan ulang ke relawan yang sama bersifat idempoten.
                    }

                    if (!$override) {
                        throw IncidentDomainException::conflict('Tiket sudah memiliki penanggung jawab aktif. Gunakan override dengan alasan untuk mengalihkan.', [
                            'active_assignment_id' => $active->id,
                        ]);
                    }

                    $this->workflow->endActiveAssignment($incident, $admin, 'REASSIGNED', $reason);
                }

                $this->workflow->verifyIfNeeded($incident, $admin);

                $wasHandled = in_array($incident->status, [IncidentReport::STATUS_ASSIGNED, IncidentReport::STATUS_IN_PROGRESS], true);
                if ($wasHandled) {
                    $incident->assigned_to = null;
                    $incident->assigned_at = null;
                }

                $assignment = IncidentAssignment::create([
                    'incident_id' => $incident->id,
                    'caretaker_id' => $caretakerId,
                    'assigned_by' => $admin->id,
                    'status' => IncidentAssignment::STATUS_OFFERED,
                    'offer_expires_at' => now()->addMinutes(IncidentAssignment::OFFER_TTL_MINUTES),
                    'note' => $override ? $reason : null,
                ]);

                $incident->notification_status = IncidentReport::NOTIFY_PENDING;
                $this->workflow->changeStatus(
                    $incident,
                    $admin,
                    IncidentReport::STATUS_WAITING_ASSIGNMENT,
                    $wasHandled ? 'Penanggung jawab dialihkan admin. Menunggu relawan baru menerima tugas.' : 'Tugas ditawarkan ke relawan. Menunggu relawan menerima.'
                );
                $incident->save();

                $this->audit->log(
                    $admin,
                    $override ? 'incident.assign_override' : 'incident.assign',
                    'incident_report',
                    $incident->id,
                    'SUCCESS',
                    $override ? $reason : null
                );

                return $assignment;
            });
        } catch (IncidentDomainException $e) {
            // Dicatat setelah rollback agar jejak konflik tidak ikut terhapus (EIR-08, IT-15).
            if ($e->errorCode === 'CONFLICT') {
                $this->audit->log($admin, 'incident.assign', 'incident_report', $incidentId, 'CONFLICT', $e->getMessage());
            }
            throw $e;
        } catch (QueryException $e) {
            // Unik parsial menolak penugasan aktif kedua dari permintaan bersamaan (IT-15).
            $this->audit->log($admin, 'incident.assign', 'incident_report', $incidentId, 'CONFLICT', 'Penugasan bersamaan ditolak database.');
            throw IncidentDomainException::conflict('Tiket sudah memiliki penanggung jawab aktif.');
        }

        $this->workflow->flushEvents();

        if ($assignment->wasRecentlyCreated) {
            $this->notifyCaretaker($assignment);
        }

        return $assignment->fresh();
    }

    /**
     * POST /caretaker/tasks/{id}/accept
     */
    public function accept(User $caretaker, string $assignmentId): IncidentAssignment
    {
        // Kedaluwarsa diproses lebih dulu dalam transaksinya sendiri agar tidak ikut di-rollback.
        $this->expireStaleOffers();

        $assignment = DB::transaction(function () use ($caretaker, $assignmentId) {
            $assignment = $this->ownAssignment($caretaker, $assignmentId);

            if ($assignment->status === IncidentAssignment::STATUS_ACCEPTED) {
                return $assignment; // Retry setelah berhasil.
            }

            if ($assignment->ended_reason === 'TIMEOUT') {
                throw IncidentDomainException::conflict('Tawaran tugas sudah kedaluwarsa (lebih dari 15 menit). Tiket kembali ke antrean.');
            }

            if ($assignment->status !== IncidentAssignment::STATUS_OFFERED) {
                throw IncidentDomainException::conflict('Tugas ini sudah tidak dapat diterima.');
            }

            $incident = IncidentReport::whereKey($assignment->incident_id)->lockForUpdate()->firstOrFail();
            if ($incident->isFinal() || $incident->status === IncidentReport::STATUS_RESOLVED) {
                throw IncidentDomainException::conflict('Tiket sudah tidak memerlukan penanganan.');
            }

            $assignment->status = IncidentAssignment::STATUS_ACCEPTED;
            $assignment->responded_at = now();
            $assignment->save();

            $incident->assigned_to = $caretaker->id;
            $incident->assigned_at = now();
            $this->workflow->changeStatus($incident, $caretaker, IncidentReport::STATUS_ASSIGNED, 'Relawan menerima tugas.');
            $incident->save();

            $this->audit->log($caretaker, 'incident.task_accepted', 'incident_assignment', $assignment->id);

            return $assignment;
        });

        return $assignment->fresh('incident');
    }

    /**
     * POST /caretaker/tasks/{id}/decline
     */
    public function decline(User $caretaker, string $assignmentId, ?string $reason = null): IncidentAssignment
    {
        $assignment = DB::transaction(function () use ($caretaker, $assignmentId, $reason) {
            $assignment = $this->ownAssignment($caretaker, $assignmentId);

            if ($assignment->status === IncidentAssignment::STATUS_DECLINED) {
                return $assignment;
            }

            if ($assignment->status !== IncidentAssignment::STATUS_OFFERED) {
                throw IncidentDomainException::conflict('Tugas ini sudah tidak dapat ditolak.');
            }

            $assignment->status = IncidentAssignment::STATUS_DECLINED;
            $assignment->ended_reason = 'DECLINED';
            $assignment->decline_reason = $reason;
            $assignment->responded_at = now();
            $assignment->save();

            $this->returnToQueue($assignment, $caretaker);
            $this->audit->log($caretaker, 'incident.task_declined', 'incident_assignment', $assignment->id, 'SUCCESS', $reason);

            return $assignment;
        });

        return $assignment->fresh();
    }

    /**
     * Tawaran tanpa respons dalam 15 menit dianggap ditolak (PRD 8.7).
     * Dipanggil dari command terjadwal dan sebelum membaca antrean atau tugas.
     */
    public function expireStaleOffers(): int
    {
        $stale = IncidentAssignment::where('status', IncidentAssignment::STATUS_OFFERED)
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<', now())
            ->pluck('id');

        $count = 0;
        foreach ($stale as $id) {
            $count += DB::transaction(function () use ($id) {
                $assignment = IncidentAssignment::whereKey($id)->lockForUpdate()->first();
                if ($assignment === null || !$assignment->isOfferExpired()) {
                    return 0;
                }

                $this->expire($assignment);

                return 1;
            });
        }

        return $count;
    }

    /**
     * GET /admin/incidents/{id}/candidates: rekomendasi bersifat tambahan (EIR-06).
     */
    public function candidates(IncidentReport $incident): array
    {
        $declinedIds = IncidentAssignment::where('incident_id', $incident->id)
            ->where('status', IncidentAssignment::STATUS_DECLINED)
            ->pluck('caretaker_id')
            ->all();

        $activeCounts = IncidentAssignment::whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
            ->select('caretaker_id', DB::raw('COUNT(*) as total'))
            ->groupBy('caretaker_id')
            ->pluck('total', 'caretaker_id');

        $candidates = CaretakerProfile::with('user:id,name,is_active')
            ->get()
            ->filter(fn (CaretakerProfile $p) => $p->user !== null && $p->user->is_active !== false)
            ->map(function (CaretakerProfile $p) use ($incident, $declinedIds, $activeCounts) {
                $distance = null;
                if ($p->last_location_lat !== null && $p->last_location_lng !== null
                    && $incident->location_lat !== null && $incident->location_lng !== null) {
                    $distance = (int) round($this->haversine(
                        $incident->location_lat,
                        $incident->location_lng,
                        $p->last_location_lat,
                        $p->last_location_lng
                    ));
                }

                return [
                    'caretaker_id' => $p->user_id,
                    'name' => $p->user->name,
                    'phone' => $p->phone,
                    'is_available' => $p->is_available,
                    'active_task_count' => (int) ($activeCounts[$p->user_id] ?? 0),
                    'territory_match' => $incident->territory_id !== null
                        && in_array($incident->territory_id, $p->territory_ids ?? [], true),
                    'distance_m' => $distance,
                    'declined_before' => in_array($p->user_id, $declinedIds, true),
                ];
            })
            ->sort(function (array $a, array $b) {
                return [!$a['is_available'], $a['declined_before'], $a['active_task_count'], !$a['territory_match'], $a['distance_m'] ?? PHP_INT_MAX]
                    <=> [!$b['is_available'], $b['declined_before'], $b['active_task_count'], !$b['territory_match'], $b['distance_m'] ?? PHP_INT_MAX];
            })
            ->values()
            ->all();

        return $candidates;
    }

    private function ownAssignment(User $caretaker, string $assignmentId): IncidentAssignment
    {
        $assignment = IncidentAssignment::whereKey($assignmentId)->lockForUpdate()->first();

        // Tugas orang lain disamarkan sebagai 404 (CORE-03).
        if ($assignment === null || $assignment->caretaker_id !== $caretaker->id) {
            throw IncidentDomainException::notFound('Tugas tidak ditemukan.');
        }

        return $assignment;
    }

    private function expire(IncidentAssignment $assignment): void
    {
        $assignment->status = IncidentAssignment::STATUS_DECLINED;
        $assignment->ended_reason = 'TIMEOUT';
        $assignment->save();

        $this->returnToQueue($assignment, null);
        $this->audit->log(null, 'incident.task_timeout', 'incident_assignment', $assignment->id, 'SUCCESS', 'Tidak ada respons dalam 15 menit.');
    }

    private function returnToQueue(IncidentAssignment $assignment, ?User $actor): void
    {
        $incident = IncidentReport::whereKey($assignment->incident_id)->lockForUpdate()->first();
        if ($incident === null || $incident->isFinal()) {
            return;
        }

        $hasOtherActive = IncidentAssignment::where('incident_id', $incident->id)
            ->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
            ->exists();

        if (!$hasOtherActive && in_array($incident->status, [IncidentReport::STATUS_RECEIVED, IncidentReport::STATUS_ASSIGNED], true)) {
            $incident->assigned_to = null;
            $incident->assigned_at = null;
            $this->workflow->changeStatus($incident, $actor, IncidentReport::STATUS_WAITING_ASSIGNMENT, 'Tugas tidak diterima relawan. Tiket kembali ke antrean penugasan.');
        }
    }

    private function notifyCaretaker(IncidentAssignment $assignment): void
    {
        $incident = IncidentReport::find($assignment->incident_id);
        $caretaker = User::find($assignment->caretaker_id);
        if ($incident === null) {
            return;
        }

        $status = IncidentReport::NOTIFY_FAILED;

        try {
            if ($caretaker === null) {
                throw new \RuntimeException('Akun relawan tidak ditemukan.');
            }

            $notification = new IncidentTaskOffered(
                $assignment->id,
                $incident->id,
                $incident->ticket_code,
                $incident->category,
                $incident->urgency
            );
            // Laravel meng-clone notifikasi saat mengirim; id ditetapkan di sini agar baris dapat dilacak.
            $notification->id = (string) Str::uuid();
            $caretaker->notify($notification);

            // Notifikasi in-app tersimpan di pusat notifikasi = terkirim (PRD 4.8).
            $stored = DB::table('notifications')->where('id', $notification->id)->update(['status' => 'SENT', 'updated_at' => now()]);
            if ($stored !== 1) {
                throw new \RuntimeException('Notifikasi in-app tidak tersimpan.');
            }
            $status = IncidentReport::NOTIFY_SENT;
        } catch (Throwable $e) {
            // Kegagalan kirim tidak membatalkan penugasan dan tidak dilaporkan sebagai terkirim.
            $this->audit->log(null, 'incident.notification', 'incident_assignment', $assignment->id, 'FAILED', $e->getMessage());
        }

        $incident->notification_status = $status;
        $incident->save();
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }
}
