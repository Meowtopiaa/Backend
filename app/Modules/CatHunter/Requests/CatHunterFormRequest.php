<?php

namespace App\Modules\CatHunter\Requests;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Basis request CatHunter: kegagalan validasi memakai format ApiResponse (AGENTS.md aturan #5).
 */
abstract class CatHunterFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error('Data yang dikirim tidak valid.', 422, 'VALIDATION_ERROR', $validator->errors()->toArray())
        );
    }
}
