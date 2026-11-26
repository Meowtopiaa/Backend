<?php

namespace App\Modules\IncidentReport\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Audit aksi penting modul insiden ke tabel Core audit_logs (PRD 4.8).
 * Tidak menyimpan token, password, nomor telepon, atau koordinat.
 */
class IncidentAuditLogger
{
    public function log(?User $actor, string $action, string $subjectType, ?string $subjectId, string $result = 'SUCCESS', ?string $reason = null): void
    {
        DB::table('audit_logs')->insert([
            'id' => (string) Str::ulid(),
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'result' => $result,
            'reason' => $reason !== null ? Str::limit($reason, 1000) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
