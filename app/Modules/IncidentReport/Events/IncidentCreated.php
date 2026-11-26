<?php

namespace App\Modules\IncidentReport\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * PRD 9.3: dikirim setelah tiket tersimpan. Isi: report_id, category, cat_id, urgency.
 */
class IncidentCreated
{
    use Dispatchable;

    public function __construct(
        public readonly string $reportId,
        public readonly string $category,
        public readonly ?string $catId,
        public readonly string $urgency,
    ) {}
}
