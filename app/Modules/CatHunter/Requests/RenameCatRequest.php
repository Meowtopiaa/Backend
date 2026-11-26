<?php

namespace App\Modules\CatHunter\Requests;

class RenameCatRequest extends CatHunterFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} .\x27-]*$/u'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Nama hanya boleh berisi huruf, angka, spasi, titik, apostrof, atau tanda hubung.',
        ];
    }
}