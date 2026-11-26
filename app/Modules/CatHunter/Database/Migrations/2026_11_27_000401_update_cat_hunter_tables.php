<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penyesuaian skema CatHunter ke aturan bisnis final:
     * tanpa poin, tanpa kuis/kondisi fisik, daily cooldown, dan hak nama permanen.
     *
     * Tabel lama (`user_cat_dex`, `cat_encounters`, `cat_encounter_turns`) dari migrasi
     * 2026_01_01_000400 diganti dengan tabel ber-prefix modul `cat_hunter_*`.
     * Migrasi lama sengaja tidak diubah.
     */
    public function up(): void
    {
        Schema::dropIfExists('cat_encounter_turns');
        Schema::dropIfExists('cat_encounters');
        Schema::dropIfExists('user_cat_dex');

        Schema::create('cat_hunter_user_cat_dex', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('cat_id', 26);
            $table->unsignedInteger('encounter_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->boolean('is_favorite_avatar')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'cat_id']);
            $table->index(['user_id', 'is_favorite_avatar']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cat_id')->references('id')->on('cats')->cascadeOnDelete();
        });

        Schema::create('cat_hunter_encounters', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('cat_id', 26);
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['cat_id', 'scanned_at']);
            $table->index(['user_id', 'cat_id', 'scanned_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cat_id')->references('id')->on('cats')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cat_hunter_encounters');
        Schema::dropIfExists('cat_hunter_user_cat_dex');

        // Kembalikan skema lama dari migrasi 2026_01_01_000400.
        Schema::create('cat_encounters', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('cat_id', 26);
            $table->string('mood')->default('CALM');
            $table->integer('trust')->default(0);
            $table->integer('turn_count')->default(0);
            $table->string('status')->default('ACTIVE');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->integer('points_awarded')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cat_id')->references('id')->on('cats')->cascadeOnDelete();
        });

        Schema::create('cat_encounter_turns', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('encounter_id', 26);
            $table->integer('turn_no');
            $table->string('action');
            $table->integer('trust_after');
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('cat_encounters')->cascadeOnDelete();
        });

        Schema::create('user_cat_dex', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->char('cat_id', 26);
            $table->string('state')->default('SEEN');
            $table->timestamp('tamed_at')->nullable();
            $table->integer('times_encountered')->default(1);
            $table->timestamp('last_greeted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'cat_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('cat_id')->references('id')->on('cats')->cascadeOnDelete();
        });
    }
};
