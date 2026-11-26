<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            if (!Schema::hasColumn('cats', 'primary_color')) {
                $table->string('primary_color', 80)->nullable()->after('color_pattern');
            }
            if (!Schema::hasColumn('cats', 'fur_pattern')) {
                $table->string('fur_pattern', 80)->nullable()->after('primary_color');
            }
            if (!Schema::hasColumn('cats', 'is_vaccinated')) {
                $table->boolean('is_vaccinated')->nullable()->after('is_sterilized');
            }
            if (!Schema::hasColumn('cats', 'vaccinated_at')) {
                $table->date('vaccinated_at')->nullable()->after('is_vaccinated');
            }
            if (!Schema::hasColumn('cats', 'vaccination_notes')) {
                $table->text('vaccination_notes')->nullable()->after('vaccinated_at');
            }
            if (!Schema::hasColumn('cats', 'temperament')) {
                $table->string('temperament', 160)->nullable()->after('description');
            }
            if (!Schema::hasColumn('cats', 'special_needs')) {
                $table->text('special_needs')->nullable()->after('temperament');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('cats', 'special_needs') ? 'special_needs' : null,
                Schema::hasColumn('cats', 'temperament') ? 'temperament' : null,
                Schema::hasColumn('cats', 'vaccination_notes') ? 'vaccination_notes' : null,
                Schema::hasColumn('cats', 'vaccinated_at') ? 'vaccinated_at' : null,
                Schema::hasColumn('cats', 'is_vaccinated') ? 'is_vaccinated' : null,
                Schema::hasColumn('cats', 'fur_pattern') ? 'fur_pattern' : null,
                Schema::hasColumn('cats', 'primary_color') ? 'primary_color' : null,
            ]));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
