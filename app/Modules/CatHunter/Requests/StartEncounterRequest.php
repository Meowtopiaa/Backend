<?php

namespace App\Modules\CatHunter\Requests;

class StartEncounterRequest extends CatHunterFormRequest
{
    public function rules(): array
    {
        return [
            // Opsional: payload QR (MTP1.C.<token>) atau kode manual kalung. Bila diisi, wajib cocok dengan kucing pada URL.
            'code' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.max' => 'Kode QR terlalu panjang.',
        ];
    }
}