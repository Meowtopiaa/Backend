<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MEOW-29: Pastikan kolom changes pada feeding_spot_histories memakai tipe jsonb di PostgreSQL
 * tanpa mengedit migration sebelumnya yang sudah di-merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('feeding_spot_histories')) {
            DB::statement('ALTER TABLE feeding_spot_histories ALTER COLUMN changes TYPE jsonb USING changes::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasTable('feeding_spot_histories')) {
            DB::statement('ALTER TABLE feeding_spot_histories ALTER COLUMN changes TYPE json USING changes::json');
        }
    }
};
