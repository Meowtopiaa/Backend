<?php

namespace App\Modules\IncidentReport\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\CaretakerProfile;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Requests\CaretakerProfileRequest;
use App\Modules\IncidentReport\Services\IncidentAuditLogger;
use App\Modules\IncidentReport\Services\IncidentPresenter;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * CRUD /admin/caretakers: daftar relawan penerima penugasan insiden.
 * Tidak menetapkan role Spatie (itu wewenang super_admin, PRD 3.1).
 */
class AdminCaretakerController extends Controller
{
    public function __construct(
        private readonly IncidentPresenter $presenter,
        private readonly IncidentAuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $query = CaretakerProfile::with('user:id,name,email')->orderByDesc('is_available')->orderBy('updated_at', 'desc');
        if ($request->has('available')) {
            $query->where('is_available', $request->boolean('available'));
        }

        return ApiResponse::success(
            $query->get()->map(fn (CaretakerProfile $p) => $this->presenter->caretaker($p))->values()->all(),
            'Daftar relawan insiden.'
        );
    }

    public function show(string $userId)
    {
        return ApiResponse::success($this->presenter->caretaker($this->find($userId)), 'Detail relawan insiden.');
    }

    public function store(CaretakerProfileRequest $request)
    {
        $data = $request->validated();
        $profile = CaretakerProfile::create($this->attributes($data) + [
            'user_id' => $data['user_id'],
            'is_available' => $data['is_available'] ?? true,
        ]);
        $this->audit->log($request->user(), 'incident.caretaker_created', 'caretaker_profile', $profile->user_id);

        return ApiResponse::created($this->presenter->caretaker($profile->fresh()), 'Relawan insiden terdaftar.');
    }

    public function update(CaretakerProfileRequest $request, string $userId)
    {
        $profile = $this->find($userId);
        $profile->fill($this->attributes($request->validated()))->save();
        $this->audit->log($request->user(), 'incident.caretaker_updated', 'caretaker_profile', $profile->user_id);

        return ApiResponse::success($this->presenter->caretaker($profile->fresh()), 'Data relawan diperbarui.');
    }

    public function destroy(Request $request, string $userId)
    {
        $profile = $this->find($userId);

        $hasActive = IncidentAssignment::where('caretaker_id', $userId)
            ->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
            ->exists();
        if ($hasActive) {
            throw IncidentDomainException::conflict('Relawan masih memiliki tugas aktif. Alihkan tugasnya terlebih dahulu.');
        }

        $profile->delete();
        $this->audit->log($request->user(), 'incident.caretaker_deleted', 'caretaker_profile', $userId);

        return ApiResponse::success(['user_id' => $userId], 'Relawan dihapus dari daftar penugasan. Riwayat penugasan tetap tersimpan.');
    }

    private function find(string $userId): CaretakerProfile
    {
        return CaretakerProfile::with('user:id,name,email')->find($userId)
            ?? throw IncidentDomainException::notFound('Relawan tidak ditemukan.');
    }

    private function attributes(array $data): array
    {
        $attributes = array_intersect_key($data, array_flip(['is_available', 'phone', 'territory_ids']));

        if (array_key_exists('last_location', $data)) {
            $attributes['last_location_lat'] = $data['last_location']['lat'] ?? null;
            $attributes['last_location_lng'] = $data['last_location']['lng'] ?? null;
        }

        return $attributes;
    }
}
