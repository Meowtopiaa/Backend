<?php

namespace App\Modules\IncidentReport\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\VetContact;
use App\Modules\IncidentReport\Requests\VetContactRequest;
use App\Modules\IncidentReport\Services\IncidentAuditLogger;
use App\Modules\IncidentReport\Services\IncidentPresenter;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * CRUD /admin/vets (EIR-12). Data kontak klinik hanya diisi data mitra yang benar.
 */
class AdminVetController extends Controller
{
    public function __construct(
        private readonly IncidentPresenter $presenter,
        private readonly IncidentAuditLogger $audit,
    ) {}

    public function index()
    {
        return ApiResponse::success(
            VetContact::orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (VetContact $v) => $this->presenter->vet($v, true))->values()->all(),
            'Daftar kontak dokter hewan.'
        );
    }

    public function show(string $id)
    {
        return ApiResponse::success($this->presenter->vet($this->find($id), true), 'Detail kontak dokter hewan.');
    }

    public function store(VetContactRequest $request)
    {
        $vet = VetContact::create($this->attributes($request->validated()) + ['is_active' => true]);
        $this->audit->log($request->user(), 'incident.vet_created', 'vet_contact', $vet->id);

        return ApiResponse::created($this->presenter->vet($vet->fresh(), true), 'Kontak dokter hewan tersimpan.');
    }

    public function update(VetContactRequest $request, string $id)
    {
        $vet = $this->find($id);
        $vet->fill($this->attributes($request->validated()))->save();
        $this->audit->log($request->user(), 'incident.vet_updated', 'vet_contact', $vet->id);

        return ApiResponse::success($this->presenter->vet($vet->fresh(), true), 'Kontak dokter hewan diperbarui.');
    }

    public function destroy(Request $request, string $id)
    {
        $vet = $this->find($id);
        $vet->delete();
        $this->audit->log($request->user(), 'incident.vet_deleted', 'vet_contact', $id);

        return ApiResponse::success(['id' => $id], 'Kontak dokter hewan dihapus.');
    }

    private function find(string $id): VetContact
    {
        return VetContact::find($id) ?? throw IncidentDomainException::notFound('Kontak dokter hewan tidak ditemukan.');
    }

    private function attributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(['name', 'address', 'phone', 'whatsapp', 'open_hours', 'is_active']));

        if (array_key_exists('location', $data)) {
            $attributes['latitude'] = $data['location']['lat'] ?? null;
            $attributes['longitude'] = $data['location']['lng'] ?? null;
        }

        return $attributes;
    }
}
