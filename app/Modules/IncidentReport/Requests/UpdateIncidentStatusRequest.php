<?php

namespace App\Modules\IncidentReport\Requests;

use App\Modules\IncidentReport\Models\IncidentReport;
use Illuminate\Validation\Rule;

/**
 * POST /incidents/{id}/status (EIR-07). Body frontend: { status, note }.
 */
class UpdateIncidentStatusRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(IncidentReport::STATUSES)],
            'note' => ['required', 'string', 'max:1000'],
            // Opsional: kondisi kucing setelah ditangani (PRD 5.6). Default UNDER_CARE saat RESOLVED.
            'cat_health_status' => ['nullable', 'string', Rule::in(['HEALTHY', 'UNDER_CARE'])],
            'duplicate_of' => ['nullable', 'string', 'size:26'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status tiket tidak dikenali.',
            'note.required' => 'Catatan wajib diisi untuk riwayat tiket.',
            'note.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
