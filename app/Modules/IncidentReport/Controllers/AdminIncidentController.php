<?php

namespace App\Modules\IncidentReport\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\IncidentReport;
use App\Modules\IncidentReport\Requests\AssignIncidentRequest;
use App\Modules\IncidentReport\Requests\CloseIncidentRequest;
use App\Modules\IncidentReport\Requests\UpdateIncidentRequest;
use App\Modules\IncidentReport\Requests\VerifyIncidentRequest;
use App\Modules\IncidentReport\Services\IncidentAccess;
use App\Modules\IncidentReport\Services\IncidentAuditLogger;
use App\Modules\IncidentReport\Services\IncidentDispatchService;
use App\Modules\IncidentReport\Services\IncidentPresenter;
use App\Modules\IncidentReport\Services\IncidentWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Antrean dan dispatch admin insiden (PRD 8.7, EIR-05, EIR-06, EIR-08, EIR-09, EIR-13).
 * Akses baca: incident.view + incident.dispatch. Tulis: incident.dispatch. Tutup: incident.close.
 */
class AdminIncidentController extends Controller
{
    public function __construct(
        private readonly IncidentWorkflowService $workflow,
        private readonly IncidentDispatchService $dispatch,
        private readonly IncidentPresenter $presenter,
        private readonly IncidentAuditLogger $audit,
    ) {}

    /**
     * GET /admin/incidents?status=&urgency=&category=&source=&cat_id=&q=&unassigned=&from=&to=
     * Urutan: urgensi tertinggi lalu terbaru. Daftar tanpa nomor kontak dan media.
     */
    public function index(Request $request)
    {
        $this->dispatch->expireStaleOffers();

        $query = IncidentReport::withCount('media')
            ->with(['cat:id,name,unique_code,presence_status,health_status', 'reporter:id,name', 'assignee:id,name', 'activeAssignment.caretaker:id,name']);

        $statuses = $this->listFilter($request, 'status', IncidentReport::STATUSES);
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        } elseif (!$request->boolean('include_final')) {
            $query->whereNotIn('status', IncidentReport::FINAL_STATUSES);
        }

        if (($urgencies = $this->listFilter($request, 'urgency', IncidentReport::URGENCIES)) !== []) {
            $query->whereIn('urgency', $urgencies);
        }
        if (($categories = $this->listFilter($request, 'category', IncidentReport::CATEGORIES)) !== []) {
            $query->whereIn('category', $categories);
        }
        if (($sources = $this->listFilter($request, 'source', IncidentReport::SOURCES)) !== []) {
            $query->whereIn('source', $sources);
        }
        if ($request->filled('cat_id')) {
            $query->where('cat_id', (string) $request->query('cat_id'));
        }
        if ($request->filled('q')) {
            $query->where('ticket_code', 'like', '%' . strtoupper(trim((string) $request->query('q'))) . '%');
        }
        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_to');
        }
        if ($from = $this->dateFilter($request, 'from')) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to = $this->dateFilter($request, 'to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $query->orderByRaw("CASE urgency WHEN 'HIGH' THEN 0 WHEN 'MEDIUM' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = $query->paginate(IncidentReportController::perPage($request));

        return ApiResponse::success(
            collect($page->items())->map(fn (IncidentReport $i) => $this->presenter->incident($i, IncidentAccess::VIEW_ADMIN))->all(),
            'Antrean laporan insiden.',
            200,
            IncidentReportController::paginationMeta($page)
        );
    }

    public function show(string $id)
    {
        $this->dispatch->expireStaleOffers();

        return ApiResponse::success($this->detail($id), 'Detail laporan insiden.');
    }

    /** PATCH /admin/incidents/{id}: koreksi urgensi dengan alasan (diaudit). */
    public function update(UpdateIncidentRequest $request, string $id)
    {
        $incident = $this->find($id);
        if ($incident->isFinal()) {
            throw IncidentDomainException::conflict('Tiket final tidak dapat diubah.');
        }

        $data = $request->validated();
        if ($incident->urgency !== $data['urgency']) {
            DB::transaction(function () use ($incident, $data, $request) {
                $this->audit->log($request->user(), 'incident.urgency_changed', 'incident_report', $incident->id, 'SUCCESS', "{$incident->urgency} -> {$data['urgency']}: {$data['reason']}");
                $incident->urgency = $data['urgency'];
                $incident->save();
            });
        }

        return ApiResponse::success($this->detail($id), 'Urgensi tiket diperbarui.');
    }

    /** POST /admin/incidents/{id}/verify: IncidentVerified + poin incident.report. */
    public function verify(VerifyIncidentRequest $request, string $id)
    {
        $result = $this->workflow->verify($request->user(), $id, $request->validated('note'), $request->validated('urgency'));

        return ApiResponse::success([
            'incident' => $this->detail($result['incident']->id),
            'reward' => $result['reward'],
        ], $result['reward'] === null ? 'Tiket sudah diverifikasi sebelumnya.' : 'Laporan diverifikasi.');
    }

    /** GET /admin/incidents/{id}/candidates: rekomendasi relawan (tambahan, bukan otomatis). */
    public function candidates(string $id)
    {
        $incident = $this->find($id);

        return ApiResponse::success([
            'handled_by_admin_only' => $this->presenter->handledByAdminOnly($incident),
            'candidates' => $this->dispatch->candidates($incident),
        ], 'Rekomendasi relawan berdasarkan ketersediaan, wilayah, dan jarak.');
    }

    /** POST /admin/incidents/{id}/assign. */
    public function assign(AssignIncidentRequest $request, string $id)
    {
        $data = $request->validated();
        $assignment = $this->dispatch->assign(
            $request->user(),
            $id,
            $data['caretaker_id'],
            (bool) ($data['override'] ?? false),
            $data['reason'] ?? null
        );

        return ApiResponse::success([
            'assignment' => $this->presenter->assignmentSummary($assignment->load('caretaker:id,name')),
            'incident' => $this->detail($id),
        ], 'Tugas ditawarkan ke relawan. Status penugasan mengikuti respons relawan.');
    }

    /** POST /admin/incidents/{id}/close (incident.close). */
    public function close(CloseIncidentRequest $request, string $id)
    {
        $data = $request->validated();
        $this->workflow->close($request->user(), $id, trim($data['note']), $data['cat_health_status'] ?? null);

        return ApiResponse::success($this->detail($id), 'Tiket ditutup setelah peninjauan.');
    }

    /**
     * GET /admin/incidents/hotspots?days=30&category= (EIR-13).
     * Agregasi grid ~110 m (0.001 derajat) dihitung di server; hanya untuk admin.
     */
    public function hotspots(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $categories = $this->listFilter($request, 'category', IncidentReport::CATEGORIES);
        $cell = 0.001;

        $rows = IncidentReport::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotIn('status', [IncidentReport::STATUS_REJECTED, IncidentReport::STATUS_DUPLICATE])
            ->when($categories !== [], fn ($q) => $q->whereIn('category', $categories))
            ->get(['location_lat', 'location_lng', 'category', 'status']);

        $cells = [];
        foreach ($rows as $row) {
            if ($row->location_lat === null || $row->location_lng === null) {
                continue;
            }
            $gy = (int) floor($row->location_lat / $cell);
            $gx = (int) floor($row->location_lng / $cell);
            $key = "{$gy}:{$gx}";
            $cells[$key] ??= [
                'lat' => round(($gy + 0.5) * $cell, 6),
                'lng' => round(($gx + 0.5) * $cell, 6),
                'count' => 0,
                'open_count' => 0,
                'categories' => [],
            ];
            $cells[$key]['count']++;
            if (!in_array($row->status, [IncidentReport::STATUS_RESOLVED, IncidentReport::STATUS_CLOSED], true)) {
                $cells[$key]['open_count']++;
            }
            $cells[$key]['categories'][$row->category] = ($cells[$key]['categories'][$row->category] ?? 0) + 1;
        }

        $cells = array_values($cells);
        usort($cells, fn ($a, $b) => $b['count'] <=> $a['count']);

        return ApiResponse::success($cells, 'Peta hotspot insiden.', 200, [
            'days' => $days,
            'cell_size_deg' => $cell,
            'total_incidents' => $rows->count(),
        ]);
    }

    private function find(string $id): IncidentReport
    {
        $incident = IncidentReport::find($id);
        if ($incident === null) {
            throw IncidentDomainException::notFound();
        }

        return $incident;
    }

    private function detail(string $id): array
    {
        $incident = IncidentReport::withCount('media')->find($id);
        if ($incident === null) {
            throw IncidentDomainException::notFound();
        }

        return $this->presenter->incident($incident, IncidentAccess::VIEW_ADMIN, true);
    }

    private function listFilter(Request $request, string $key, array $allowed): array
    {
        $raw = $request->query($key);
        if ($raw === null || $raw === '') {
            return [];
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_intersect(array_map(fn ($v) => strtoupper(trim((string) $v)), $values), $allowed));
    }

    private function dateFilter(Request $request, string $key): ?\Illuminate\Support\Carbon
    {
        if (!$request->filled($key)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse((string) $request->query($key))->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
