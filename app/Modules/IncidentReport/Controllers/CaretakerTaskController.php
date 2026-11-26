<?php

namespace App\Modules\IncidentReport\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IncidentReport\Exceptions\IncidentDomainException;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Requests\DeclineTaskRequest;
use App\Modules\IncidentReport\Services\IncidentAccess;
use App\Modules\IncidentReport\Services\IncidentDispatchService;
use App\Modules\IncidentReport\Services\IncidentPresenter;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/**
 * Endpoint relawan (PRD 8.7): GET /caretaker/tasks, POST /caretaker/tasks/{id}/accept|decline.
 * Relawan ditentukan oleh profil caretaker_profiles (dibuat admin insiden), bukan string role.
 */
class CaretakerTaskController extends Controller
{
    public function __construct(
        private readonly IncidentDispatchService $dispatch,
        private readonly IncidentAccess $access,
        private readonly IncidentPresenter $presenter,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensureCaretaker($user);

        $this->dispatch->expireStaleOffers();

        $tasks = IncidentAssignment::with('incident')
            ->where('caretaker_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)
                    ->orWhere(fn ($q) => $q->where('status', IncidentAssignment::STATUS_DONE)
                        ->where('updated_at', '>=', now()->subDays(30)));
            })
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (IncidentAssignment $a) => $a->incident !== null)
            ->sortBy(fn (IncidentAssignment $a) => match ($a->status) {
                IncidentAssignment::STATUS_OFFERED => 0,
                IncidentAssignment::STATUS_ACCEPTED => 1,
                default => 2,
            });

        return ApiResponse::success(
            $tasks->map(fn (IncidentAssignment $a) => $this->presenter->task($a))->values()->all(),
            'Daftar tugas insiden Anda.'
        );
    }

    public function accept(Request $request, string $id)
    {
        $this->ensureCaretaker($request->user());
        $assignment = $this->dispatch->accept($request->user(), $id);

        return ApiResponse::success($this->presenter->task($assignment), 'Tugas diterima. Anda menjadi penanggung jawab utama tiket ini.');
    }

    public function decline(DeclineTaskRequest $request, string $id)
    {
        $this->ensureCaretaker($request->user());
        $assignment = $this->dispatch->decline($request->user(), $id, $request->validated('reason'));

        return ApiResponse::success([
            'id' => $assignment->id,
            'status' => $assignment->status,
            'responded_at' => $assignment->responded_at?->toIso8601ZuluString(),
        ], 'Tugas ditolak. Tiket kembali ke antrean admin.');
    }

    private function ensureCaretaker($user): void
    {
        if (!$this->access->isCaretaker($user)) {
            throw IncidentDomainException::forbidden('Akun Anda belum terdaftar sebagai relawan insiden.');
        }
    }
}
