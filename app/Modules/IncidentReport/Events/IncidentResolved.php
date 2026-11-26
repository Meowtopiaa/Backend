<?php

namespace App\Modules\IncidentReport\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * PRD 9.3: tindakan lapangan selesai. Isi: report_id, category, cat_id.
 * Untuk MISSING/DECEASED, Core yang memutuskan presence_status lewat antrean konfirmasi admin.
 */
class IncidentResolved
{
    use Dispatchable;

    public function __construct(
        public readonly string $reportId,
        public readonly string $category,
        public readonly ?string $catId,
    ) {}
}
