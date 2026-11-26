<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optimind: melengkapi tabel incident_* sesuai PRD bagian 5.5, 6.6, 7.4, dan 8.7
 * tanpa mengedit migration skeleton 2026_01_01_000600 yang sudah di-merge.
 *
 * - incident_reports: akurasi lokasi, jejak verifikasi admin (IncidentVerified),
 *   status kesehatan yang diterapkan, serta penautan duplikat (EIR-10, EIR-13).
 * - incident_status_logs.from_status boleh kosong untuk entri pertama (RECEIVED).
 * - incident_assignments: batas waktu tawaran 15 menit, alasan penolakan atau
 *   pengakhiran, dan unik parsial satu penugasan aktif per tiket (EIR-08, IT-15).
 * - incident_media: satu media hanya ditautkan ke satu tiket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_reports', function (Blueprint $table) {
            $table->decimal('location_accuracy_m', 8, 2)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->char('verified_by', 26)->nullable();
            $table->string('cat_health_applied')->nullable(); // SICK atau INJURED yang diterapkan saat verifikasi
            $table->char('possible_duplicate_of', 26)->nullable(); // EIR-13: penautan potensi duplikat
            $table->char('duplicate_of', 26)->nullable(); // diisi admin saat status DUPLICATE

            $table->index(['reporter_id', 'created_at'], 'incident_reports_reporter_created_idx');
            $table->index(['source', 'source_ref'], 'incident_reports_source_ref_idx');
            $table->index(['created_at'], 'incident_reports_created_idx');
        });

        Schema::table('incident_status_logs', function (Blueprint $table) {
            $table->string('from_status')->nullable()->change();
            $table->index(['incident_id', 'created_at'], 'incident_status_logs_incident_created_idx');
        });

        Schema::table('incident_assignments', function (Blueprint $table) {
            $table->timestamp('offer_expires_at')->nullable();
            $table->string('ended_reason')->nullable(); // DECLINED, TIMEOUT, REASSIGNED, UNASSIGNED
            $table->text('decline_reason')->nullable();
            $table->text('note')->nullable();

            $table->index(['caretaker_id', 'status'], 'incident_assignments_caretaker_status_idx');
            $table->index(['status', 'offer_expires_at'], 'incident_assignments_status_expires_idx');
        });

        Schema::table('incident_media', function (Blueprint $table) {
            $table->unique('media_id', 'incident_media_media_unique');
        });

        // Unik parsial (didukung PostgreSQL dan SQLite): satu penanggung jawab aktif per tiket.
        DB::statement("CREATE UNIQUE INDEX incident_assignments_one_active ON incident_assignments (incident_id) WHERE status IN ('OFFERED', 'ACCEPTED')");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS incident_assignments_one_active');

        Schema::table('incident_media', function (Blueprint $table) {
            $table->dropUnique('incident_media_media_unique');
        });

        Schema::table('incident_assignments', function (Blueprint $table) {
            $table->dropIndex('incident_assignments_status_expires_idx');
            $table->dropIndex('incident_assignments_caretaker_status_idx');
            $table->dropColumn(['offer_expires_at', 'ended_reason', 'decline_reason', 'note']);
        });

        DB::table('incident_status_logs')->whereNull('from_status')->update(['from_status' => '']);
        Schema::table('incident_status_logs', function (Blueprint $table) {
            $table->dropIndex('incident_status_logs_incident_created_idx');
            $table->string('from_status')->nullable(false)->change();
        });

        Schema::table('incident_reports', function (Blueprint $table) {
            $table->dropIndex('incident_reports_created_idx');
            $table->dropIndex('incident_reports_source_ref_idx');
            $table->dropIndex('incident_reports_reporter_created_idx');
            $table->dropColumn([
                'location_accuracy_m',
                'verified_at',
                'verified_by',
                'cat_health_applied',
                'possible_duplicate_of',
                'duplicate_of',
            ]);
        });
    }
};
