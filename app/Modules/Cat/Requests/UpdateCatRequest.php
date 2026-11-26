<?php

namespace App\Modules\Cat\Requests;

use App\Modules\Cat\Models\Cat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCatRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('cat.edit') || $user->can('cat.curate'));
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['gender', 'age_group', 'presence_status', 'health_status', 'adoption_status', 'verification_status'] as $field) {
            if ($this->filled($field) && is_string($this->input($field))) {
                $normalized[$field] = strtoupper(trim($this->input($field)));
            }
        }

        if ($this->filled('unique_code') && is_string($this->input('unique_code'))) {
            $normalized['unique_code'] = strtoupper(trim($this->input('unique_code')));
        }

        if (!empty($normalized)) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        $catId = (string) $this->route('id');

        return [
            'unique_code' => [
                'sometimes',
                'string',
                'max:32',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('cats', 'unique_code')->ignore($catId, 'id'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['sometimes', 'string', Rule::in(Cat::GENDERS)],
            'primary_color' => ['nullable', 'string', 'max:80'],
            'fur_pattern' => ['nullable', 'string', 'max:80'],
            'color_pattern' => ['nullable', 'string', 'max:160'],
            'age_group' => ['sometimes', 'string', Rule::in(Cat::AGE_GROUPS)],
            'is_sterilized' => ['nullable', 'boolean'],
            'is_vaccinated' => ['nullable', 'boolean'],
            'vaccinated_at' => ['nullable', 'date'],
            'vaccination_notes' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'temperament' => ['nullable', 'string', 'max:160'],
            'special_needs' => ['nullable', 'string', 'max:1000'],
            'traits' => ['nullable', 'array'],
            'traits.physical_marks' => ['nullable', 'string', 'max:255'],
            'traits.ear_tip' => ['nullable', 'string', 'max:80'],
            'traits.tail_type' => ['nullable', 'string', 'max:80'],
            'traits.eye_color' => ['nullable', 'string', 'max:80'],
            'territory_id' => ['nullable', 'string', 'size:26', 'exists:territories,id'],
            'primary_photo_media_id' => ['nullable', 'string', 'size:26', 'exists:media_files,id'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
            'photo_angle' => ['nullable', 'string', Rule::in(['front', 'left', 'right', 'tail', 'face'])],
            'presence_status' => ['sometimes', 'string', Rule::in(Cat::PRESENCE_STATUSES)],
            'health_status' => ['sometimes', 'string', Rule::in(Cat::HEALTH_STATUSES)],
            'adoption_status' => ['sometimes', 'string', Rule::in(Cat::ADOPTION_STATUSES)],
            'verification_status' => ['sometimes', 'string', Rule::in(Cat::VERIFICATION_STATUSES)],
            'status_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
