<?php

namespace App\Modules\IncidentReport\Requests;

use App\Modules\IncidentReport\Models\IncidentReport;
use Illuminate\Validation\Rule;

/** POST /admin/incidents/{id}/verify. */
class VerifyIncidentRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'urgency' => ['nullable', 'string', Rule::in(IncidentReport::URGENCIES)],
        ];
    }
}
