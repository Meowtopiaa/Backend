<?php

namespace App\Modules\IncidentReport\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\IncidentReport;
use App\Modules\IncidentReport\Models\VetContact;
use App\Modules\IncidentReport\Requests\StoreIncidentRequest;
use App\Modules\IncidentReport\Requests\UpdateIncidentStatusRequest;
use App\Modules\IncidentReport\Services\IncidentAccess;
use App\Modules\IncidentReport\Services\IncidentPresenter;
use App\Modules\IncidentReport\Services\IncidentWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Endpoint pelapor dan relawan (PRD 8.7):
 * POST /incidents †, GET /incidents/mine, GET /incidents/{id}, POST /incidents/{id}/status †, GET /vets.
 */
class IncidentReportController extends Controller
{
    public function __construct(
        private readonly IncidentWorkflowService $workflow,
        private readonly IncidentAccess $access,
        private readonly IncidentPresenter $presenter,
    ) {}

    /**
     * EIR-01..04: tiket dan status RECEIVED dikembalikan hanya setelah tersimpan.
     */
    public function store(StoreIncidentRequest $request)
    {
        $incident = $this->workflow->create($request->user(), $request->validated());

        return ApiResponse::created(
            $this->presenter->incident($incident, IncidentAccess::VIEW_REPORTER, true),
            "Laporan tersimpan dengan nomor tiket {$incident->ticket_code} dan masuk antrean admin insiden."
        );
    }

    /**
     * EIR-05: daftar tiket milik pelapor. Tanpa media dan nomor kontak.
     */
    public function mine(Request $request)
    {
        $query = IncidentReport::withCount('media')
            ->with('cat:id,name,unique_code,presence_status,health_status')
            ->where('reporter_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $status = strtoupper((string) $request->query('status', ''));
        if (in_array($status, IncidentReport::STATUSES, true)) {
            $query->where('status', $status);
        }

        $page = $query->paginate(self::perPage($request));

        return ApiResponse::success(
            collect($page->items())->map(fn (IncidentReport $i) => $this->presenter->incident($i, IncidentAccess::VIEW_REPORTER))->all(),
            'Daftar laporan insiden Anda.',
            200,
            self::paginationMeta($page)
        );
    }

    /**
     * EIR-05, FIX-02: detail dengan riwayat status untuk pelapor, relawan yang ditugaskan, atau admin.
     * Pengguna lain menerima 404 tanpa isi.
     */
    public function show(Request $request, string $id)
    {
        $incident = IncidentReport::withCount('media')->find($id);
        $view = $incident ? $this->access->viewFor($request->user(), $incident) : null;

        if ($incident === null || $view === null) {
            throw IncidentDomainException::notFound();
        }

        return ApiResponse::success($this->presenter->incident($incident, $view, true), 'Detail laporan insiden.');
    }

    /**
     * EIR-07: relawan yang menerima tugas atau admin mengubah status lewat transisi yang diizinkan.
     */
    public function updateStatus(UpdateIncidentStatusRequest $request, string $id)
    {
        $data = $request->validated();
        $incident = $this->workflow->transition(
            $request->user(),
            $id,
            $data['status'],
            trim($data['note']),
            [
                'cat_health_status' => $data['cat_health_status'] ?? null,
                'duplicate_of' => $data['duplicate_of'] ?? null,
            ]
        );

        $view = $this->access->viewFor($request->user(), $incident) ?? IncidentAccess::VIEW_CARETAKER;

        return ApiResponse::success(
            $this->presenter->incident($incident->loadCount('media'), $view, true),
            'Status tiket diperbarui.'
        );
    }

    /**
     * EIR-12: kontak dokter hewan aktif. Data tidak diisi fiktif; daftar boleh kosong.
     */
    public function vets()
    {
        $vets = VetContact::where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success(
            $vets->map(fn (VetContact $v) => $this->presenter->vet($v))->values()->all(),
            $vets->isEmpty() ? 'Belum ada kontak dokter hewan mitra yang terdaftar.' : 'Daftar kontak dokter hewan.'
        );
    }

    public static function perPage(Request $request, int $default = 20): int
    {
        return max(1, min(50, (int) $request->query('per_page', $default)));
    }

    public static function paginationMeta($page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
            'has_more' => $page->hasMorePages(),
        ];
    }
}
