<?php

namespace App\Modules\IncidentReport\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * PRD 9.3: admin memverifikasi laporan. Isi: report_id, category, cat_id.
 */
class IncidentVerified
{
    use Dispatchable;

    public function __construct(
        public readonly string $reportId,
        public readonly string $category,
        public readonly ?string $catId,
    ) {}
}
