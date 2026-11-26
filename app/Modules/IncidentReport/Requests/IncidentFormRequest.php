<?php

namespace App\Modules\IncidentReport\Requests;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Basis FormRequest modul insiden: kegagalan validasi memakai format PRD 8.1
 * `{ success: false, code: VALIDATION_ERROR, message, errors, request_id }`.
 * Otorisasi per objek dilakukan di controller/service lewat IncidentAccess.
 */
abstract class IncidentFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(ApiResponse::error(
            $validator->errors()->first() ?: 'Data yang dikirim tidak valid.',
            422,
            'VALIDATION_ERROR',
            $validator->errors()->toArray()
        ));
    }

    /** Aturan koordinat `{ lat, lng, accuracy_m? }` (PRD 8.1, EIR-03). */
    protected function locationRules(string $field, bool $required): array
    {
        return [
            $field => [$required ? 'required' : 'nullable', 'array'],
            "{$field}.lat" => ["required_with:{$field}", 'numeric', 'between:-90,90'],
            "{$field}.lng" => ["required_with:{$field}", 'numeric', 'between:-180,180'],
            "{$field}.accuracy_m" => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /** Menolak koordinat 0,0 yang biasanya berarti GPS gagal. */
    protected function rejectNullIsland(Validator $validator, string $field): void
    {
        $validator->after(function (Validator $v) use ($field) {
            $lat = $this->input("{$field}.lat");
            $lng = $this->input("{$field}.lng");
            if (is_numeric($lat) && is_numeric($lng) && (float) $lat === 0.0 && (float) $lng === 0.0) {
                $v->errors()->add($field, 'Koordinat tidak valid. Tentukan lokasi lewat GPS atau pin di peta.');
            }
        });
    }
}
