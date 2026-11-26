<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom pendukung game CatHunter pada tabel master `cats` (tetap satu tabel kucing).
     * - is_name_locked: nama kucing sudah dikunci permanen oleh pemain (5x pertemuan).
     * - quote: dialog lucu kucing yang ditampilkan di Cat-Dex.
     */
    public function up(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            if (!Schema::hasColumn('cats', 'is_name_locked')) {
                $table->boolean('is_name_locked')->default(false)->after('name');
            }
            if (!Schema::hasColumn('cats', 'quote')) {
                $table->string('quote')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('cats', 'quote') ? 'quote' : null,
                Schema::hasColumn('cats', 'is_name_locked') ? 'is_name_locked' : null,
            ]));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
