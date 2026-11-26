<?php

namespace App\Modules\CatHunter\Requests;

class CompleteEncounterRequest extends CatHunterFormRequest
{
    public function rules(): array
    {
        return [
            // Sesi mengelus selesai setelah 2 fase.
            'phases_completed' => ['required', 'integer', 'in:2'],
        ];
    }

    public function messages(): array
    {
        return [
            'phases_completed.in' => 'Sesi mengelus baru selesai setelah 2 fase.',
        ];
    }
}