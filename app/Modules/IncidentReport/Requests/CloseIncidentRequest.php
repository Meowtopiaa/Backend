<?php

namespace App\Modules\IncidentReport\Requests;

use Illuminate\Validation\Rule;

/** POST /admin/incidents/{id}/close (incident.close). */
class CloseIncidentRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:1000'],
            'cat_health_status' => ['nullable', 'string', Rule::in(['HEALTHY', 'UNDER_CARE'])],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'Catatan peninjauan wajib diisi.'];
    }
}
