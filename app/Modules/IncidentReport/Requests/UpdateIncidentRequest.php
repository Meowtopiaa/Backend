<?php

namespace App\Modules\IncidentReport\Requests;

use App\Modules\IncidentReport\Models\IncidentReport;
use Illuminate\Validation\Rule;

/** PATCH /admin/incidents/{id}: koreksi urgensi dengan alasan. */
class UpdateIncidentRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return [
            'urgency' => ['required', 'string', Rule::in(IncidentReport::URGENCIES)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
