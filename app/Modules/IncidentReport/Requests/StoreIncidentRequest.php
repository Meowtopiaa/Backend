<?php

namespace App\Modules\IncidentReport\Requests;

use App\Modules\FeedingSpot\Services\FeedingSpotManagementService;
use App\Modules\IncidentReport\Models\IncidentReport;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /incidents (EIR-01..03, OQ-11). Body sesuai IncidentDraft.toJson() di frontend.
 */
class StoreIncidentRequest extends IncidentFormRequest
{
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(IncidentReport::REPORTABLE_CATEGORIES)],
            'urgency' => ['required', 'string', Rule::in(IncidentReport::URGENCIES)],
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            ...$this->locationRules('location', true),
            'location_note' => ['nullable', 'string', 'max:255'],
            // OQ-11: foto atau video opsional, maksimal 5 berkas dari POST /media.
            'media_ids' => ['nullable', 'array', 'max:5'],
            'media_ids.*' => ['string', 'size:26', 'distinct'],
            'cat_id' => ['nullable', 'string', Rule::exists('cats', 'id')->whereNull('deleted_at')],
            'source' => ['nullable', 'string', Rule::in(IncidentReport::SOURCES)],
            'source_ref' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'Kategori laporan tidak dikenali.',
            'urgency.in' => 'Urgensi harus LOW, MEDIUM, atau HIGH.',
            'description.required' => 'Deskripsi kondisi wajib diisi.',
            'description.min' => 'Deskripsi minimal 10 karakter.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'location.required' => 'Tentukan lokasi lewat GPS atau pilih pin di peta.',
            'location.lat.between' => 'Koordinat lokasi tidak valid.',
            'location.lng.between' => 'Koordinat lokasi tidak valid.',
            'location_note.max' => 'Patokan lokasi maksimal 255 karakter.',
            'media_ids.max' => 'Maksimal 5 foto atau video.',
            'cat_id.exists' => 'Kucing tidak ditemukan.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->rejectNullIsland($validator, 'location');

        // Titik insiden hanya di dalam kampus Telkom University Bandung, memakai poligon
        // batas kampus yang sama dengan modul Spot Makan.
        $validator->after(function (Validator $v) {
            $lat = $this->input('location.lat');
            $lng = $this->input('location.lng');
            if ($v->errors()->has('location') || $v->errors()->has('location.lat') || $v->errors()->has('location.lng')
                || !is_numeric($lat) || !is_numeric($lng)) {
                return;
            }
            if (!FeedingSpotManagementService::isPointInsideTelkomCampus((float) $lat, (float) $lng)) {
                $v->errors()->add('location', 'Lokasi berada di luar kampus Telkom University Bandung. Laporan insiden hanya untuk area kampus.');
            }
        });
    }
}
