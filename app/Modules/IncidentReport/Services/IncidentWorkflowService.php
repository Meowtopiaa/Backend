<?php

namespace App\Modules\IncidentReport\Services;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use App\Modules\Core\Services\CatStatusService;
use App\Modules\Core\Services\PointService;
use App\Modules\IncidentReport\Events\IncidentCreated;
use App\Modules\IncidentReport\Events\IncidentResolved;
use App\Modules\IncidentReport\Events\IncidentVerified;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Models\IncidentMedia;
use App\Modules\IncidentReport\Models\IncidentReport;
use App\Modules\IncidentReport\Models\IncidentStatusLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alur tiket insiden: pembuatan (EIR-01..04, EIR-10), transisi status (5.5, EIR-07),
 * verifikasi admin (IncidentVerified, poin incident.report), dan penyelesaian (IncidentResolved).
 *
 * Status kucing hanya lewat CatStatusService::change() dan poin hanya lewat PointService::award().
 */
class IncidentWorkflowService
{
    /** Matriks transisi PRD 5.5. ASSIGNED hanya lewat penerimaan tugas relawan. */
    public const TRANSITIONS = [
        IncidentReport::STATUS_RECEIVED => [
            IncidentReport::STATUS_WAITING_ASSIGNMENT,
            IncidentReport::STATUS_IN_PROGRESS,
            IncidentReport::STATUS_REJECTED,
            IncidentReport::STATUS_DUPLICATE,
        ],
        IncidentReport::STATUS_WAITING_ASSIGNMENT => [
            IncidentReport::STATUS_IN_PROGRESS,
            IncidentReport::STATUS_REJECTED,
            IncidentReport::STATUS_DUPLICATE,
        ],
        IncidentReport::STATUS_ASSIGNED => [
            IncidentReport::STATUS_IN_PROGRESS,
            IncidentReport::STATUS_WAITING_ASSIGNMENT,
            IncidentReport::STATUS_REJECTED,
            IncidentReport::STATUS_DUPLICATE,
        ],
        IncidentReport::STATUS_IN_PROGRESS => [
            IncidentReport::STATUS_RESOLVED,
            IncidentReport::STATUS_WAITING_ASSIGNMENT,
        ],
        IncidentReport::STATUS_RESOLVED => [
            IncidentReport::STATUS_CLOSED,
            IncidentReport::STATUS_IN_PROGRESS,
        ],
        IncidentReport::STATUS_CLOSED => [],
        IncidentReport::STATUS_REJECTED => [],
        IncidentReport::STATUS_DUPLICATE => [],
    ];

    /** Transisi yang boleh diajukan relawan yang menerima tugas (EIR-07). */
    public const CARETAKER_TRANSITIONS = [
        IncidentReport::STATUS_ASSIGNED => [IncidentReport::STATUS_IN_PROGRESS],
        IncidentReport::STATUS_IN_PROGRESS => [IncidentReport::STATUS_RESOLVED],
    ];

    /** Status yang hanya boleh ditetapkan pemegang incident.close. */
    public const CLOSE_STATUSES = [
        IncidentReport::STATUS_CLOSED,
        IncidentReport::STATUS_REJECTED,
        IncidentReport::STATUS_DUPLICATE,
    ];

    /** @var array<int, object> */
    private array $pendingEvents = [];

    public function __construct(
        private readonly IncidentAccess $access,
        private readonly IncidentAuditLogger $audit,
        private readonly CatStatusService $catStatus,
        private readonly PointService $points,
    ) {}

    // ------------------------------------------------------------------
    // Pembuatan tiket
    // ------------------------------------------------------------------

    public function create(User $reporter, array $data): IncidentReport
    {
        $incident = DB::transaction(function () use ($reporter, $data) {
            $cat = !empty($data['cat_id']) ? Cat::find($data['cat_id']) : null;
            $mediaIds = array_values(array_unique($data['media_ids'] ?? []));
            $this->assertMediaUsable($reporter, $mediaIds);

            $source = $data['source'] ?? IncidentReport::SOURCE_DIRECT;
            $sourceRef = isset($data['source_ref']) && trim((string) $data['source_ref']) !== ''
                ? trim((string) $data['source_ref'])
                : null;

            $incident = IncidentReport::create([
                'ticket_code' => $this->generateTicketCode(),
                'reporter_id' => $reporter->id,
                'cat_id' => $cat?->id,
                'category' => $data['category'],
                'urgency' => $data['urgency'],
                'description' => trim($data['description']),
                'location_lat' => (float) $data['location']['lat'],
                'location_lng' => (float) $data['location']['lng'],
                'location_accuracy_m' => isset($data['location']['accuracy_m']) ? (float) $data['location']['accuracy_m'] : null,
                'location_note' => isset($data['location_note']) && trim((string) $data['location_note']) !== ''
                    ? trim((string) $data['location_note'])
                    : null,
                'territory_id' => $cat?->territory_id,
                'source' => $source,
                'source_ref' => $sourceRef,
                'status' => IncidentReport::STATUS_RECEIVED,
                // FIX-09: belum ada notifikasi yang diminta; tidak mengklaim relawan sudah diberi tahu.
                'notification_status' => IncidentReport::NOTIFY_NOT_REQUESTED,
                'possible_duplicate_of' => $this->findPossibleDuplicate($reporter, $cat?->id, $data['category'], $source, $sourceRef),
            ]);

            foreach ($mediaIds as $mediaId) {
                IncidentMedia::create(['incident_id' => $incident->id, 'media_id' => $mediaId]);
            }

            $this->log($incident, null, IncidentReport::STATUS_RECEIVED, $reporter, 'Tiket tersimpan dan masuk antrean.');

            // EIR-10 / CAT-08: kategori NEW_CAT menambah baris antrean kurasi Rekans Caps.
            if ($incident->category === 'NEW_CAT') {
                DB::table('unidentified_sightings')->insert([
                    'id' => (string) Str::ulid(),
                    'reporter_id' => $reporter->id,
                    'media_id' => $mediaIds[0] ?? null,
                    'location_lat' => $incident->location_lat,
                    'location_lng' => $incident->location_lng,
                    'notes' => Str::limit($incident->description, 1000),
                    'source' => 'INCIDENT_NEW_CAT',
                    'incident_report_id' => $incident->id,
                    'status' => 'OPEN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->pendingEvents[] = new IncidentCreated($incident->id, $incident->category, $incident->cat_id, $incident->urgency);

            return $incident;
        });

        $this->flushEvents();

        return $incident->fresh();
    }

    // ------------------------------------------------------------------
    // Transisi status (POST /incidents/{id}/status)
    // ------------------------------------------------------------------

    /**
     * @param array{cat_health_status?: string|null, duplicate_of?: string|null} $options
     */
    public function transition(User $actor, string $incidentId, string $toStatus, string $note, array $options = []): IncidentReport
    {
        $incident = DB::transaction(function () use ($actor, $incidentId, $toStatus, $note, $options) {
            $incident = IncidentReport::whereKey($incidentId)->lockForUpdate()->first();
            if (!$incident) {
                throw IncidentDomainException::notFound();
            }

            $isAdmin = $this->access->canDispatch($actor);
            $caretakerAssignment = $isAdmin ? null : $this->access->caretakerAssignment($actor, $incident, [IncidentAssignment::STATUS_ACCEPTED]);

            if (!$isAdmin && $caretakerAssignment === null) {
                // Pelapor dan relawan yang berhak melihat tiket menerima 403; pengguna lain disamarkan 404.
                $canSee = $incident->reporter_id === $actor->id
                    || $this->access->caretakerAssignment($actor, $incident) !== null;

                throw $canSee
                    ? IncidentDomainException::forbidden('Hanya relawan yang menerima tugas aktif atau admin insiden yang dapat mengubah status.')
                    : IncidentDomainException::notFound();
            }

            $from = $incident->status;

            // Retry dengan target sama tidak mengubah apa pun.
            if ($from === $toStatus) {
                return $incident;
            }

            if ($toStatus === IncidentReport::STATUS_ASSIGNED) {
                throw IncidentDomainException::conflict('Status ASSIGNED ditetapkan lewat penugasan relawan, bukan perubahan status manual.');
            }

            if (!in_array($toStatus, self::TRANSITIONS[$from] ?? [], true)) {
                throw IncidentDomainException::conflict("Transisi status dari {$from} ke {$toStatus} tidak diizinkan.");
            }

            if (!$isAdmin && !in_array($toStatus, self::CARETAKER_TRANSITIONS[$from] ?? [], true)) {
                throw IncidentDomainException::conflict("Relawan tidak dapat mengubah status dari {$from} ke {$toStatus}.");
            }

            if (in_array($toStatus, self::CLOSE_STATUSES, true) && !$this->access->canClose($actor)) {
                throw IncidentDomainException::forbidden('Hanya admin insiden dengan izin incident.close yang dapat menutup, menolak, atau menandai duplikat.');
            }

            $this->applyTransition($incident, $actor, $from, $toStatus, $note, $options);

            return $incident;
        });

        $this->flushEvents();

        return $incident->fresh();
    }

    /**
     * POST /admin/incidents/{id}/close (incident.close): RESOLVED -> CLOSED.
     */
    public function close(User $admin, string $incidentId, string $note, ?string $catHealthStatus = null): IncidentReport
    {
        if (!$this->access->canClose($admin)) {
            throw IncidentDomainException::forbidden();
        }

        return $this->transition($admin, $incidentId, IncidentReport::STATUS_CLOSED, $note, ['cat_health_status' => $catHealthStatus]);
    }

    /**
     * POST /admin/incidents/{id}/verify: admin memverifikasi laporan (IncidentVerified).
     * RECEIVED berpindah ke WAITING_ASSIGNMENT; urgensi boleh dikoreksi.
     *
     * @return array{incident: IncidentReport, reward: array|null}
     */
    public function verify(User $admin, string $incidentId, ?string $note = null, ?string $urgency = null): array
    {
        $reward = null;

        $incident = DB::transaction(function () use ($admin, $incidentId, $note, $urgency, &$reward) {
            $incident = IncidentReport::whereKey($incidentId)->lockForUpdate()->first();
            if (!$incident) {
                throw IncidentDomainException::notFound();
            }

            if ($incident->isFinal()) {
                throw IncidentDomainException::conflict('Tiket yang sudah ditutup, ditolak, atau duplikat tidak dapat diverifikasi.');
            }

            if ($urgency !== null && $urgency !== $incident->urgency) {
                $this->audit->log($admin, 'incident.urgency_changed', 'incident_report', $incident->id, 'SUCCESS', "{$incident->urgency} -> {$urgency}");
                $incident->urgency = $urgency;
                $incident->save();
            }

            $reward = $this->verifyIfNeeded($incident, $admin);

            if ($incident->status === IncidentReport::STATUS_RECEIVED) {
                $this->changeStatus($incident, $admin, IncidentReport::STATUS_WAITING_ASSIGNMENT, $note ?: 'Laporan diverifikasi admin dan menunggu penanggung jawab.');
            }

            return $incident;
        });

        $this->flushEvents();

        return ['incident' => $incident->fresh(), 'reward' => $reward];
    }

    /**
     * Verifikasi sekali per tiket: health_status untuk SICK/INJURED dan poin incident.report.
     * Dipanggil di dalam transaksi pemanggil.
     */
    public function verifyIfNeeded(IncidentReport $incident, User $admin): ?array
    {
        if ($incident->verified_at !== null) {
            return null;
        }

        $incident->verified_at = now();
        $incident->verified_by = $admin->id;

        // PRD 5.6: laporan SICK atau INJURED mengubah health_status saat admin memverifikasi.
        if ($incident->cat_id !== null && in_array($incident->category, IncidentReport::HEALTH_CATEGORIES, true)) {
            $cat = Cat::whereKey($incident->cat_id)->lockForUpdate()->first();
            if ($cat !== null && $cat->health_status !== $incident->category) {
                try {
                    $this->catStatus->change(
                        $cat,
                        'health_status',
                        $incident->category,
                        $admin,
                        'IncidentReport',
                        "Laporan insiden {$incident->ticket_code} diverifikasi admin.",
                        'incident_report',
                        $incident->id
                    );
                    $incident->cat_health_applied = $incident->category;
                } catch (Throwable $e) {
                    $this->audit->log($admin, 'incident.cat_health_change', 'incident_report', $incident->id, 'FAILED', $e->getMessage());
                }
            }
        }

        $incident->save();

        $reward = $this->awardReportPoints($incident, $admin);

        $this->audit->log($admin, 'incident.verified', 'incident_report', $incident->id);
        $this->pendingEvents[] = new IncidentVerified($incident->id, $incident->category, $incident->cat_id);

        return $reward;
    }

    // ------------------------------------------------------------------
    // Utilitas yang dipakai service penugasan
    // ------------------------------------------------------------------

    public function changeStatus(IncidentReport $incident, ?User $actor, string $toStatus, ?string $note): void
    {
        $from = $incident->status;
        if ($from === $toStatus) {
            return;
        }

        $incident->status = $toStatus;
        $incident->save();

        $this->log($incident, $from, $toStatus, $actor, $note);
    }

    public function log(IncidentReport $incident, ?string $from, string $to, ?User $actor, ?string $note): IncidentStatusLog
    {
        return IncidentStatusLog::create([
            'incident_id' => $incident->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);
    }

    public function queueEvent(object $event): void
    {
        $this->pendingEvents[] = $event;
    }

    public function flushEvents(): void
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        foreach ($events as $event) {
            event($event);
        }
    }

    /**
     * Mengakhiri penugasan aktif (OFFERED/ACCEPTED) saat admin mengambil alih, melepas, atau menutup tiket.
     */
    public function endActiveAssignment(IncidentReport $incident, User $actor, string $reason, ?string $note = null): ?IncidentAssignment
    {
        $active = IncidentAssignment::where('incident_id', $incident->id)
            ->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->first();

        if ($active === null) {
            return null;
        }

        $active->status = IncidentAssignment::STATUS_CANCELLED;
        $active->ended_reason = $reason;
        $active->note = $note;
        $active->save();

        $this->audit->log($actor, 'incident.assignment_ended', 'incident_assignment', $active->id, 'SUCCESS', $reason . ($note ? ": {$note}" : ''));

        return $active;
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function applyTransition(IncidentReport $incident, User $actor, string $from, string $to, string $note, array $options): void
    {
        switch ($to) {
            case IncidentReport::STATUS_WAITING_ASSIGNMENT:
                // Admin melepas penanggung jawab; tiket kembali ke antrean penugasan.
                $this->verifyIfNeeded($incident, $actor);
                $this->endActiveAssignment($incident, $actor, 'UNASSIGNED', $note);
                $incident->assigned_to = null;
                $incident->assigned_at = null;
                break;

            case IncidentReport::STATUS_IN_PROGRESS:
                if (in_array($from, [IncidentReport::STATUS_RECEIVED, IncidentReport::STATUS_WAITING_ASSIGNMENT], true)) {
                    // Admin menangani langsung (mis. tiket kucing teradopsi, ADP-13).
                    $this->verifyIfNeeded($incident, $actor);
                    $this->endActiveAssignment($incident, $actor, 'ADMIN_HANDLING', $note);
                    $incident->assigned_to = $actor->id;
                    $incident->assigned_at = now();
                }
                if ($from === IncidentReport::STATUS_RESOLVED) {
                    $incident->resolved_at = null;
                }
                break;

            case IncidentReport::STATUS_RESOLVED:
                $incident->resolved_at = now();
                $incident->resolution_note = $note;
                IncidentAssignment::where('incident_id', $incident->id)
                    ->where('status', IncidentAssignment::STATUS_ACCEPTED)
                    ->update(['status' => IncidentAssignment::STATUS_DONE, 'updated_at' => now()]);
                // PRD 5.6: health_status kembali saat tiket selesai. Default UNDER_CARE karena
                // menyatakan HEALTHY adalah keputusan admin saat menutup tiket.
                $this->restoreCatHealth($incident, $actor, $options['cat_health_status'] ?? 'UNDER_CARE');
                $this->pendingEvents[] = new IncidentResolved($incident->id, $incident->category, $incident->cat_id);
                break;

            case IncidentReport::STATUS_CLOSED:
                $incident->closed_at = now();
                if (!empty($options['cat_health_status'])) {
                    $this->restoreCatHealth($incident, $actor, $options['cat_health_status'], true);
                }
                $this->audit->log($actor, 'incident.closed', 'incident_report', $incident->id, 'SUCCESS', $note);
                break;

            case IncidentReport::STATUS_REJECTED:
            case IncidentReport::STATUS_DUPLICATE:
                if ($to === IncidentReport::STATUS_DUPLICATE) {
                    $incident->duplicate_of = $this->resolveDuplicateTarget($incident, $options['duplicate_of'] ?? $incident->possible_duplicate_of);
                }
                $this->endActiveAssignment($incident, $actor, $to, $note);
                $incident->closed_at = now();
                $this->audit->log($actor, 'incident.' . strtolower($to), 'incident_report', $incident->id, 'SUCCESS', $note);
                break;
        }

        $incident->status = $to;
        $incident->save();

        $this->log($incident, $from, $to, $actor, $note);
    }

    private function restoreCatHealth(IncidentReport $incident, User $actor, string $target, bool $fromUnderCare = false): void
    {
        if ($incident->cat_id === null || $incident->cat_health_applied === null) {
            return;
        }

        $cat = Cat::whereKey($incident->cat_id)->lockForUpdate()->first();
        if ($cat === null) {
            return;
        }

        $current = $cat->health_status;
        $eligible = $current === $incident->cat_health_applied || ($fromUnderCare && $current === 'UNDER_CARE');
        if (!$eligible || $current === $target) {
            return;
        }

        try {
            $this->catStatus->change(
                $cat,
                'health_status',
                $target,
                $actor,
                'IncidentReport',
                "Tiket insiden {$incident->ticket_code} selesai ditangani.",
                'incident_report',
                $incident->id
            );
        } catch (Throwable $e) {
            $this->audit->log($actor, 'incident.cat_health_change', 'incident_report', $incident->id, 'FAILED', $e->getMessage());
        }
    }

    private function awardReportPoints(IncidentReport $incident, User $admin): array
    {
        // Admin tidak memverifikasi laporannya sendiri untuk mendapat poin.
        if ($incident->reporter_id === $admin->id) {
            return ['status' => 'NOT_ELIGIBLE', 'points' => 0, 'reason' => 'Pelapor adalah verifikator.'];
        }

        $reporter = User::find($incident->reporter_id);
        if ($reporter === null) {
            return ['status' => 'NOT_ELIGIBLE', 'points' => 0, 'reason' => 'Akun pelapor tidak ditemukan.'];
        }

        try {
            $result = $this->points->award(
                $reporter,
                'incident.report',
                'IncidentReport',
                'incident_report',
                $incident->id,
                "incident.report:{$incident->id}"
            );

            return [
                'status' => 'CREDITED',
                'points' => (int) $result['points'],
                'transaction_id' => $result['transaction_id'],
                'reward_capped' => (bool) ($result['reward_capped'] ?? false),
            ];
        } catch (Throwable $e) {
            // Kegagalan poin tidak membatalkan verifikasi; status poin dilaporkan apa adanya.
            $this->audit->log($admin, 'incident.reward', 'incident_report', $incident->id, 'FAILED', $e->getMessage());

            return ['status' => 'FAILED', 'points' => 0];
        }
    }

    private function assertMediaUsable(User $reporter, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $owned = DB::table('media_files')
            ->whereIn('id', $mediaIds)
            ->where('owner_id', $reporter->id)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $missing = array_values(array_diff($mediaIds, $owned));
        if ($missing !== []) {
            throw IncidentDomainException::validation('Media tidak ditemukan atau bukan milik Anda.', [
                'media_ids' => ['Media tidak ditemukan atau bukan milik Anda.'],
            ]);
        }

        if (IncidentMedia::whereIn('media_id', $mediaIds)->exists()) {
            throw IncidentDomainException::validation('Media sudah dipakai pada tiket lain.', [
                'media_ids' => ['Media sudah dipakai pada tiket lain.'],
            ]);
        }
    }

    /**
     * EIR-10/EIR-13: tautkan potensi duplikat tanpa memblokir laporan baru yang sudah
     * dikonfirmasi pengguna sebagai kejadian baru di aplikasi.
     */
    private function findPossibleDuplicate(User $reporter, ?string $catId, string $category, string $source, ?string $sourceRef): ?string
    {
        if ($source !== IncidentReport::SOURCE_DIRECT && $sourceRef !== null) {
            $sameSource = IncidentReport::where('reporter_id', $reporter->id)
                ->where('source', $source)
                ->where('source_ref', $sourceRef)
                ->whereNotIn('status', IncidentReport::FINAL_STATUSES)
                ->latest('created_at')
                ->value('id');

            if ($sameSource !== null) {
                return $sameSource;
            }
        }

        if ($catId !== null) {
            return IncidentReport::where('cat_id', $catId)
                ->where('category', $category)
                ->whereNotIn('status', IncidentReport::FINAL_STATUSES)
                ->where('created_at', '>=', now()->subDay())
                ->latest('created_at')
                ->value('id');
        }

        return null;
    }

    private function resolveDuplicateTarget(IncidentReport $incident, ?string $targetId): ?string
    {
        if ($targetId === null || $targetId === '') {
            return null;
        }

        if ($targetId === $incident->id || !IncidentReport::whereKey($targetId)->exists()) {
            throw IncidentDomainException::validation('Tiket rujukan duplikat tidak valid.', [
                'duplicate_of' => ['Tiket rujukan duplikat tidak valid.'],
            ]);
        }

        return $targetId;
    }

    private function generateTicketCode(): string
    {
        $prefix = 'INC-' . now()->utc()->format('ymd') . '-';

        for ($i = 0; $i < 10; $i++) {
            $code = $prefix . strtoupper(Str::random(6));
            if (!IncidentReport::where('ticket_code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper((string) Str::ulid());
    }
}
