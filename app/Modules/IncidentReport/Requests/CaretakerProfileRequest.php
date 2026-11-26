<?php

namespace App\Modules\IncidentReport\Requests;

use Illuminate\Validation\Rule;

/** CRUD /admin/caretakers. */
class CaretakerProfileRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'user_id' => $isCreate
                ? ['required', 'string', 'size:26', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('caretaker_profiles', 'user_id')]
                : ['prohibited'],
            'is_available' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s\-]{6,30}$/'],
            'territory_ids' => ['nullable', 'array'],
            'territory_ids.*' => ['string', 'size:26', 'distinct', Rule::exists('territories', 'id')],
            ...$this->locationRules('last_location', false),
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'Pengguna ini sudah terdaftar sebagai relawan.',
            'user_id.exists' => 'Pengguna tidak ditemukan.',
        ];
    }
}
