<?php

namespace App\Modules\IncidentReport\Requests;

/** CRUD /admin/vets (EIR-12). */
class VetContactRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $phone = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-]{6,30}$/'];

        return [
            'name' => [$required, 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => $phone,
            'whatsapp' => $phone,
            ...$this->locationRules('location', false),
            'open_hours' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
