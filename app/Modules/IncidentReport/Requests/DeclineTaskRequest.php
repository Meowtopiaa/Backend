<?php

namespace App\Modules\IncidentReport\Requests;

/** POST /caretaker/tasks/{id}/decline. */
class DeclineTaskRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['nullable', 'string', 'max:500']];
    }
}
