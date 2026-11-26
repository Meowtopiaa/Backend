<?php

namespace App\Modules\IncidentReport\Requests;

use Illuminate\Validation\Rule;

/** POST /admin/incidents/{id}/assign (EIR-06, EIR-08). */
class AssignIncidentRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return [
            'caretaker_id' => ['required', 'string', 'size:26', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'override' => ['nullable', 'boolean'],
            // Override admin wajib beralasan dan dicatat.
            'reason' => ['required_if:override,true,1', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'caretaker_id.exists' => 'Relawan tidak ditemukan.',
            'reason.required_if' => 'Alasan wajib diisi saat override penanggung jawab.',
        ];
    }
}
