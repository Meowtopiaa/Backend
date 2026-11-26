<?php

namespace App\Modules\IncidentReport\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\IncidentReport\Models\CaretakerProfile;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Models\IncidentReport;
use App\Modules\IncidentReport\Models\IncidentStatusLog;
use App\Modules\IncidentReport\Models\VetContact;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Serialisasi respons insiden. Bentuk JSON mengikuti `incident_model.dart` di frontend.
 *
 * Privasi (PRD 4.6, EIR-09, NFR-04):
 * - Nomor kontak tidak pernah ada di daftar; telepon pelapor hanya di detail admin.
 * - Media hanya di detail untuk admin dan relawan yang ditugaskan; daftar cukup media_count.
 * - Pelapor melihat label peran ("Admin Insiden", "Relawan"), bukan nama petugas.
 */
class IncidentPresenter
{
    public function __construct(private readonly IncidentAccess $access) {}

    public function incident(IncidentReport $incident, string $view, bool $detail = false): array
    {
        $incident->loadMissing(['cat:id,name,unique_code,presence_status,health_status']);

        $data = [
            'id' => $incident->id,
            'ticket_code' => $incident->ticket_code,
            'category' => $incident->category,
            'urgency' => $incident->urgency,
            'description' => $incident->description,
            'status' => $incident->status,
            'cat_id' => $incident->cat_id,
            'cat' => $incident->cat ? [
                'id' => $incident->cat->id,
                'name' => $incident->cat->name,
                'unique_code' => $incident->cat->unique_code,
            ] : null,
            'location' => $this->location($incident),
            'location_note' => $incident->location_note,
            'territory_id' => $incident->territory_id,
            'source' => $incident->source,
            'source_ref' => $incident->source_ref,
            'assigned_at' => $incident->assigned_at?->toIso8601ZuluString(),
            'resolved_at' => $incident->resolved_at?->toIso8601ZuluString(),
            'closed_at' => $incident->closed_at?->toIso8601ZuluString(),
            'resolution_note' => $incident->resolution_note,
            'notification_status' => $incident->notification_status,
            'has_assignee' => $incident->assigned_to !== null,
            'media_count' => $incident->media_count ?? $incident->media()->count(),
            'created_at' => $incident->created_at?->toIso8601ZuluString(),
            'updated_at' => $incident->updated_at?->toIso8601ZuluString(),
        ];

        if ($view === IncidentAccess::VIEW_ADMIN) {
            $incident->loadMissing(['reporter:id,name,phone', 'assignee:id,name', 'activeAssignment.caretaker:id,name']);
            $active = $incident->activeAssignment;

            $data += [
                'reporter' => $incident->reporter ? array_filter([
                    'id' => $incident->reporter->id,
                    'name' => $incident->reporter->name,
                    // Nomor kontak hanya di detail, tidak di daftar (EIR-09).
                    'phone' => $detail ? $incident->reporter->phone : null,
                ], fn ($v) => $v !== null) : null,
                'assignee' => $incident->assignee ? ['id' => $incident->assignee->id, 'name' => $incident->assignee->name] : null,
                'active_assignment' => $active ? [
                    'id' => $active->id,
                    'status' => $active->status,
                    'caretaker' => $active->caretaker ? ['id' => $active->caretaker->id, 'name' => $active->caretaker->name] : null,
                    'offer_expires_at' => $active->offer_expires_at?->toIso8601ZuluString(),
                ] : null,
                'verified_at' => $incident->verified_at?->toIso8601ZuluString(),
                'possible_duplicate_of' => $incident->possible_duplicate_of,
                'duplicate_of' => $incident->duplicate_of,
                'handled_by_admin_only' => $this->handledByAdminOnly($incident),
                'presence_confirmation_required' => $incident->cat_id !== null
                    && in_array($incident->category, IncidentReport::PRESENCE_CATEGORIES, true),
            ];
        }

        if ($detail) {
            $data['status_logs'] = $this->statusLogs($incident, $view);

            if (in_array($view, [IncidentAccess::VIEW_ADMIN, IncidentAccess::VIEW_CARETAKER], true)) {
                $data['media'] = $this->media($incident);
            }

            if ($view === IncidentAccess::VIEW_ADMIN) {
                $data['assignments'] = $incident->assignments()
                    ->with('caretaker:id,name')
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn (IncidentAssignment $a) => $this->assignmentSummary($a))
                    ->values()
                    ->all();
            }
        }

        return $data;
    }

    public function task(IncidentAssignment $assignment): array
    {
        $assignment->loadMissing('incident');

        return [
            'id' => $assignment->id,
            'status' => $assignment->status,
            'offered_at' => $assignment->created_at?->toIso8601ZuluString(),
            'offer_expires_at' => $assignment->offer_expires_at?->toIso8601ZuluString(),
            'responded_at' => $assignment->responded_at?->toIso8601ZuluString(),
            'incident' => $this->incident($assignment->incident, IncidentAccess::VIEW_CARETAKER, true),
        ];
    }

    public function assignmentSummary(IncidentAssignment $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'caretaker' => $a->caretaker ? ['id' => $a->caretaker->id, 'name' => $a->caretaker->name] : null,
            'assigned_by' => $a->assigned_by,
            'offered_at' => $a->created_at?->toIso8601ZuluString(),
            'offer_expires_at' => $a->offer_expires_at?->toIso8601ZuluString(),
            'responded_at' => $a->responded_at?->toIso8601ZuluString(),
            'ended_reason' => $a->ended_reason,
            'decline_reason' => $a->decline_reason,
            'note' => $a->note,
        ];
    }

    public function vet(VetContact $vet, bool $admin = false): array
    {
        $data = [
            'id' => $vet->id,
            'name' => $vet->name,
            'address' => $vet->address,
            'phone' => $vet->phone,
            'whatsapp' => $vet->whatsapp,
            'open_hours' => $vet->open_hours,
            'location' => ($vet->latitude !== null && $vet->longitude !== null)
                ? ['lat' => $vet->latitude, 'lng' => $vet->longitude]
                : null,
        ];

        if ($admin) {
            $data['is_active'] = $vet->is_active;
            $data['created_at'] = $vet->created_at?->toIso8601ZuluString();
            $data['updated_at'] = $vet->updated_at?->toIso8601ZuluString();
        }

        return $data;
    }

    public function caretaker(CaretakerProfile $profile): array
    {
        $profile->loadMissing('user:id,name,email');

        return [
            'user_id' => $profile->user_id,
            'name' => $profile->user?->name,
            'email' => $profile->user?->email,
            'is_available' => $profile->is_available,
            'phone' => $profile->phone,
            'territory_ids' => $profile->territory_ids ?? [],
            'last_location' => ($profile->last_location_lat !== null && $profile->last_location_lng !== null)
                ? ['lat' => $profile->last_location_lat, 'lng' => $profile->last_location_lng]
                : null,
            'active_task_count' => IncidentAssignment::where('caretaker_id', $profile->user_id)
                ->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
                ->count(),
            'updated_at' => $profile->updated_at?->toIso8601ZuluString(),
        ];
    }

    public function handledByAdminOnly(IncidentReport $incident): bool
    {
        // ADP-13: tiket dari kucing teradopsi ditangani admin, bukan dispatch relawan kampus.
        if ($incident->source === IncidentReport::SOURCE_ADOPTION) {
            return true;
        }

        $incident->loadMissing('cat:id,name,unique_code,presence_status,health_status');

        return $incident->cat !== null && ($incident->cat->presence_status ?? null) === 'ADOPTED';
    }

    private function location(IncidentReport $incident): ?array
    {
        if ($incident->location_lat === null || $incident->location_lng === null) {
            return null;
        }

        $location = ['lat' => $incident->location_lat, 'lng' => $incident->location_lng];
        if ($incident->location_accuracy_m !== null) {
            $location['accuracy_m'] = $incident->location_accuracy_m;
        }

        return $location;
    }

    private function statusLogs(IncidentReport $incident, string $view): array
    {
        $logs = IncidentStatusLog::with('actor:id,name')
            ->where('incident_id', $incident->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $caretakerIds = IncidentAssignment::where('incident_id', $incident->id)->pluck('caretaker_id')->all();
        $labelCache = [];

        return $logs->map(function (IncidentStatusLog $log) use ($incident, $view, $caretakerIds, &$labelCache) {
            return [
                'id' => $log->id,
                'from_status' => $log->from_status ?: null,
                'to_status' => $log->to_status,
                'actor' => $this->actor($log, $incident, $view, $caretakerIds, $labelCache),
                'note' => $log->note,
                'created_at' => $log->created_at?->toIso8601ZuluString(),
            ];
        })->values()->all();
    }

    private function actor(IncidentStatusLog $log, IncidentReport $incident, string $view, array $caretakerIds, array &$cache): ?array
    {
        if ($log->actor_id === null) {
            return ['name' => 'Sistem'];
        }

        if ($view === IncidentAccess::VIEW_ADMIN) {
            return ['id' => $log->actor_id, 'name' => $log->actor?->name];
        }

        if ($log->actor_id === $incident->reporter_id) {
            return ['name' => 'Pelapor'];
        }

        if (!array_key_exists($log->actor_id, $cache)) {
            $cache[$log->actor_id] = match (true) {
                in_array($log->actor_id, $caretakerIds, true) => 'Relawan',
                $log->actor !== null && $this->access->canDispatch($log->actor) => 'Admin Insiden',
                default => 'Petugas',
            };
        }

        return ['name' => $cache[$log->actor_id]];
    }

    private function media(IncidentReport $incident): array
    {
        $mediaIds = $incident->media()->pluck('media_id')->all();
        if ($mediaIds === []) {
            return [];
        }

        $mediaService = app(MediaService::class);

        return DB::table('media_files')
            ->whereIn('id', $mediaIds)
            ->whereNull('deleted_at')
            ->get(['id', 'mime', 'size_bytes'])
            ->map(function ($media) use ($mediaService) {
                try {
                    $url = $mediaService->getUrl($media->id);
                } catch (Throwable) {
                    $url = null; // Disk tanpa dukungan signed URL: klien memakai GET /media/{id}/url.
                }

                return [
                    'id' => $media->id,
                    'mime' => $media->mime,
                    'size_bytes' => (int) $media->size_bytes,
                    'url' => $url,
                ];
            })
            ->values()
            ->all();
    }
}
