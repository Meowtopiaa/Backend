<?php

namespace App\Modules\CatHunter\Tests\Feature;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Models\Territory;
use App\Modules\CatHunter\Database\Seeders\CatHunterSeeder;
use App\Modules\CatHunter\Models\CatEncounter;
use App\Modules\CatHunter\Models\UserCatDex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CatHunterTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Jakarta';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeUser(string $prefix = 'hunter'): User
    {
        return User::create([
            'id' => (string) Str::ulid(),
            'name' => ucfirst($prefix) . ' Tester',
            'email' => $prefix . '-' . Str::lower(Str::random(6)) . '@student.telkomuniversity.ac.id',
            'password' => Hash::make('password'),
            'user_type' => 'MAHASISWA',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    private function makeCat(string $name, string $zoneCode = 'GKU', string $color = 'Oren', array $override = []): Cat
    {
        $territory = Territory::firstOrCreate(
            ['code' => $zoneCode],
            ['id' => (string) Str::ulid(), 'name' => $zoneCode]
        );

        $cat = Cat::create(array_merge([
            'unique_code' => 'TEST-' . Str::upper(Str::random(6)),
            'name' => $name,
            'gender' => 'MALE',
            'primary_color' => $color,
            'is_sterilized' => true,
            'temperament' => 'Ramah',
            'description' => 'Dekat gazebo',
            'quote' => 'Meow tes!',
            'territory_id' => $territory->id,
            'presence_status' => 'ON_CAMPUS',
            'health_status' => 'HEALTHY',
        ], $override));

        DB::table('cat_qr_tokens')->insert([
            'id' => (string) Str::ulid(),
            'cat_id' => $cat->id,
            'token' => 'tok' . Str::lower(Str::random(20)),
            'status' => 'ACTIVE',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cat;
    }

    private function at(string $jakartaTime): void
    {
        Carbon::setTestNow(Carbon::parse($jakartaTime, self::TZ));
    }

    private function encounter(Cat $cat, string $key)
    {
        return $this->postJson(
            "/api/v1/cat-hunter/cats/{$cat->id}/encounters",
            ['phases_completed' => 2],
            ['Idempotency-Key' => $key]
        );
    }

    /** Selesaikan N pertemuan di N hari berbeda. */
    private function meet(Cat $cat, int $times, string $startDate = '2026-03-01'): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->at(Carbon::parse($startDate, self::TZ)->addDays($i)->setTime(10, 0)->toDateTimeString());
            $this->encounter($cat, "meet-{$cat->id}-{$startDate}-{$i}")->assertStatus(201);
        }
    }

    // ------------------------------------------------------------------
    // Katalog & zona
    // ------------------------------------------------------------------

    public function test_endpoints_require_authentication(): void
    {
        $cat = $this->makeCat('Mochi');

        $this->getJson('/api/v1/cat-hunter/cats')->assertStatus(401);
        $this->getJson('/api/v1/cat-hunter/zones')->assertStatus(401);
        $this->getJson('/api/v1/cat-hunter/profile')->assertStatus(401);
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/scan-check")->assertStatus(401);
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/encounters", ['phases_completed' => 2])->assertStatus(401);
        $this->patchJson("/api/v1/cat-hunter/cats/{$cat->id}/name", ['name' => 'Budi'])->assertStatus(401);
        $this->patchJson('/api/v1/cat-hunter/profile/avatar', ['cat_id' => $cat->id])->assertStatus(401);
    }

    public function test_catalog_and_zones_with_seeded_data(): void
    {
        $this->seed(CatHunterSeeder::class);
        $this->seed(CatHunterSeeder::class); // idempotent

        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/cat-hunter/cats')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => [[
                'id', 'unique_code', 'qr_hash', 'name', 'variant', 'zone', 'zone_code', 'location_detail',
                'health_status', 'health_label', 'is_sterilized', 'personality', 'quote',
                'encounter_count', 'last_scanned_at', 'is_collected', 'can_scan_today',
                'is_name_locked', 'can_rename', 'cafe_pose',
            ]]]);

        $cats = collect($res->json('data'));
        $this->assertCount(7, $cats);
        $this->assertEqualsCanonicalizing(['oren', 'putih', 'hitam'], $cats->pluck('variant')->unique()->values()->all());
        $this->assertTrue($cats->every(fn ($c) => $c['encounter_count'] === 0 && $c['can_scan_today'] && !$c['can_rename']));
        $this->assertTrue($cats->every(fn ($c) => !empty($c['quote'])));

        // Privasi: token QR mentah tidak boleh bocor.
        $tokens = DB::table('cat_qr_tokens')->pluck('token');
        foreach ($tokens as $token) {
            $this->assertStringNotContainsString($token, $res->getContent());
        }

        $zones = collect($this->getJson('/api/v1/cat-hunter/zones')->assertOk()->json('data'));
        $this->assertSame(['TULT & Asrama', 'GKU', 'Cacuk', 'Fakultas Depan'], $zones->pluck('name')->all());
        $this->assertSame([2, 2, 1, 2], $zones->pluck('active_cats_count')->all());
        $this->assertTrue($zones->every(fn ($z) => $z['has_unvisited_cat_today']));
    }

    public function test_zone_indicator_turns_off_when_all_cats_greeted_today(): void
    {
        $this->at('2026-03-10 09:00:00');
        $user = $this->makeUser();
        $a = $this->makeCat('Aa', 'CACUK');
        $b = $this->makeCat('Bb', 'GKU');
        Sanctum::actingAs($user);

        $this->encounter($a, 'z-1')->assertStatus(201);

        $zones = collect($this->getJson('/api/v1/cat-hunter/zones')->json('data'))->keyBy('code');
        $this->assertFalse($zones['CACUK']['has_unvisited_cat_today']);
        $this->assertTrue($zones['GKU']['has_unvisited_cat_today']);
        $this->assertSame(1, $zones['CACUK']['collected_cats_count']);

        // Besok kembali menyala.
        $this->at('2026-03-11 09:00:00');
        $zones = collect($this->getJson('/api/v1/cat-hunter/zones')->json('data'))->keyBy('code');
        $this->assertTrue($zones['CACUK']['has_unvisited_cat_today']);
    }

    public function test_fit_territory_maps_to_fakultas_depan_zone(): void
    {
        $user = $this->makeUser();
        $fitCat = $this->makeCat('Selaru', 'FIT');
        Sanctum::actingAs($user);

        $item = collect($this->getJson('/api/v1/cat-hunter/cats')->assertOk()->json('data'))
            ->firstWhere('id', $fitCat->id);
        $this->assertSame('Fakultas Depan', $item['zone']);
        $this->assertSame('FAKULTAS_DEPAN', $item['zone_code']);

        $zones = collect($this->getJson('/api/v1/cat-hunter/zones')->assertOk()->json('data'))->keyBy('code');
        $this->assertSame(1, $zones['FAKULTAS_DEPAN']['active_cats_count']);
        $this->assertContains($fitCat->id, $zones['FAKULTAS_DEPAN']['resident_cat_ids']);
        $this->assertTrue($zones['FAKULTAS_DEPAN']['has_unvisited_cat_today']);
    }

    public function test_catalog_excludes_cats_not_on_campus_and_blocks_sick_cats(): void
    {
        $user = $this->makeUser();
        $onCampus = $this->makeCat('Hadir');
        $this->makeCat('Pergi', 'GKU', 'Oren', ['presence_status' => 'ADOPTED']);
        $sick = $this->makeCat('Sakit', 'GKU', 'Oren', ['health_status' => 'SICK']);
        Sanctum::actingAs($user);

        $names = collect($this->getJson('/api/v1/cat-hunter/cats')->json('data'))->pluck('name')->all();
        $this->assertContains('Hadir', $names);
        $this->assertNotContains('Pergi', $names);

        // Kucing sakit tampil (read-only) tetapi tidak bisa disapa.
        $item = collect($this->getJson('/api/v1/cat-hunter/cats')->json('data'))->firstWhere('id', $sick->id);
        $this->assertFalse($item['can_scan_today']);
        $this->encounter($sick, 'sick-1')->assertStatus(422)->assertJsonPath('code', 'CAT_NOT_AVAILABLE');
        $this->assertSame(0, CatEncounter::count());
        $this->assertNotNull($onCampus->id);
    }

    // ------------------------------------------------------------------
    // Scan-check (ScanResolver) & cooldown
    // ------------------------------------------------------------------

    public function test_scan_check_resolves_qr_and_manual_code_and_rejects_mismatch(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        $other = $this->makeCat('Boni');
        Sanctum::actingAs($user);

        $token = DB::table('cat_qr_tokens')->where('cat_id', $cat->id)->value('token');

        // Tanpa kode: hanya cek cooldown.
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/scan-check")
            ->assertOk()->assertJsonPath('data.can_scan_today', true);

        // QR kalung valid.
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/scan-check", ['code' => "MTP1.C.{$token}"])
            ->assertOk()->assertJsonPath('data.cat.id', $cat->id);

        // Kode manual (unique_code), huruf kecil pun diterima.
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/scan-check", ['code' => strtolower($cat->unique_code)])
            ->assertOk()->assertJsonPath('data.can_scan_today', true);

        // QR milik kucing lain.
        $this->postJson("/api/v1/cat-hunter/cats/{$other->id}/scan-check", ['code' => "MTP1.C.{$token}"])
            ->assertStatus(422)->assertJsonPath('code', 'QR_MISMATCH')->assertJsonStructure(['request_id']);

        // QR tidak dikenal.
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/scan-check", ['code' => 'MTP1.C.tidakada'])
            ->assertStatus(404)->assertJsonPath('code', 'QR_INVALID');

        // Kucing tidak ada.
        $this->postJson('/api/v1/cat-hunter/cats/01JNOTEXIST000000000000000/scan-check')
            ->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_daily_cooldown_blocks_second_encounter_same_day(): void
    {
        $this->at('2026-03-10 08:00:00');
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);

        $this->encounter($cat, 'cd-1')->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.encounter_count', 1)
            ->assertJsonPath('data.is_first_encounter', true)
            ->assertJsonPath('data.can_rename', false);

        // Sesi kedua di hari yang sama ditolak, tidak ada data yang bertambah.
        $this->at('2026-03-10 22:00:00');
        $this->encounter($cat, 'cd-2')->assertStatus(409)->assertJsonPath('code', 'DAILY_COOLDOWN');
        $this->assertSame(1, UserCatDex::first()->encounter_count);
        $this->assertSame(1, CatEncounter::count());

        // scan-check ikut melaporkan cooldown.
        $this->postJson("/api/v1/cat-hunter/cats/{$cat->id}/scan-check")
            ->assertOk()->assertJsonPath('data.can_scan_today', false)
            ->assertJsonPath('success', true);

        // Besok bisa lagi.
        $this->at('2026-03-11 07:00:00');
        $this->encounter($cat, 'cd-3')->assertStatus(201)->assertJsonPath('data.encounter_count', 2);
    }

    public function test_cooldown_uses_jakarta_calendar_day_not_utc(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);

        // 23:30 WIB (16:30 UTC) lalu 00:10 WIB hari berikutnya (17:10 UTC) => hari kalender berbeda.
        $this->at('2026-03-10 23:30:00');
        $this->encounter($cat, 'tz-1')->assertStatus(201);
        $this->at('2026-03-11 00:10:00');
        $this->encounter($cat, 'tz-2')->assertStatus(201)->assertJsonPath('data.encounter_count', 2);

        // 00:05 WIB (17:05 UTC kemarin) dan 23:55 WIB (16:55 UTC) di hari WIB yang sama => ditolak.
        $other = $this->makeCat('Boni');
        $this->at('2026-03-12 00:05:00');
        $this->encounter($other, 'tz-3')->assertStatus(201);
        $this->at('2026-03-12 23:55:00');
        $this->encounter($other, 'tz-4')->assertStatus(409);
    }

    // ------------------------------------------------------------------
    // Encounter: validasi, idempotensi, log
    // ------------------------------------------------------------------

    public function test_encounter_validation_idempotency_and_log(): void
    {
        $this->at('2026-03-10 08:00:00');
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);

        $url = "/api/v1/cat-hunter/cats/{$cat->id}/encounters";

        // Tanpa Idempotency-Key.
        $this->postJson($url, ['phases_completed' => 2])->assertStatus(400);

        // Validasi gagal: fase belum lengkap / kosong; format error ApiResponse.
        $this->postJson($url, ['phases_completed' => 1], ['Idempotency-Key' => 'v-1'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['phases_completed'], 'request_id']);
        $this->postJson($url, [], ['Idempotency-Key' => 'v-2'])->assertStatus(422);
        $this->assertSame(0, CatEncounter::count());

        // Kucing tidak ada.
        $this->postJson('/api/v1/cat-hunter/cats/01JNOTEXIST000000000000000/encounters', ['phases_completed' => 2], ['Idempotency-Key' => 'v-3'])
            ->assertStatus(404);

        // Sukses + replay key yang sama tidak menambah data.
        $this->postJson($url, ['phases_completed' => 2], ['Idempotency-Key' => 'ok-1'])->assertStatus(201);
        $this->postJson($url, ['phases_completed' => 2], ['Idempotency-Key' => 'ok-1'])->assertStatus(201);
        $this->assertSame(1, CatEncounter::count());
        $this->assertSame(1, UserCatDex::first()->encounter_count);

        $log = CatEncounter::first();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($cat->id, $log->cat_id);
        $this->assertNotNull($log->scanned_at);

        // last_seen_at kucing ikut tercatat (keaktifan populasi).
        $this->assertNotNull($cat->fresh()->last_seen_at);
    }

    public function test_encounters_one_to_five_unlock_rename_right(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);

        for ($i = 1; $i <= 5; $i++) {
            $this->at(Carbon::parse('2026-03-01', self::TZ)->addDays($i)->setTime(9, 0)->toDateTimeString());
            $res = $this->encounter($cat, "five-{$i}")->assertStatus(201)
                ->assertJsonPath('data.encounter_count', $i)
                ->assertJsonPath('data.can_rename', $i >= 5)
                ->assertJsonPath('data.is_name_locked', false)
                ->assertJsonPath('data.cat.encounter_count', $i);
            $this->assertSame($i === 1, $res->json('data.is_first_encounter'));
        }

        $this->assertSame(5, CatEncounter::where('user_id', $user->id)->where('cat_id', $cat->id)->count());
        $dex = UserCatDex::where('user_id', $user->id)->where('cat_id', $cat->id)->first();
        $this->assertSame(5, $dex->encounter_count);
        $this->assertNotNull($dex->last_scanned_at);
        $this->assertSame(1, UserCatDex::count());
    }

    public function test_progress_is_per_user(): void
    {
        $this->at('2026-03-10 08:00:00');
        $alice = $this->makeUser('alice');
        $bob = $this->makeUser('bob');
        $cat = $this->makeCat('Mochi');

        Sanctum::actingAs($alice);
        $this->encounter($cat, 'pu-a')->assertStatus(201);

        Sanctum::actingAs($bob);
        $item = collect($this->getJson('/api/v1/cat-hunter/cats')->json('data'))->firstWhere('id', $cat->id);
        $this->assertSame(0, $item['encounter_count']);
        $this->assertTrue($item['can_scan_today']);
        $this->encounter($cat, 'pu-b')->assertStatus(201)->assertJsonPath('data.encounter_count', 1);
    }

    // ------------------------------------------------------------------
    // Rename permanen
    // ------------------------------------------------------------------

    public function test_rename_denied_before_five_encounters(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);
        $this->meet($cat, 4);

        $this->patchJson("/api/v1/cat-hunter/cats/{$cat->id}/name", ['name' => 'Budi'], ['Idempotency-Key' => 'rn-1'])
            ->assertStatus(403)->assertJsonPath('code', 'RENAME_NOT_ALLOWED');

        // User yang belum pernah bertemu juga ditolak.
        Sanctum::actingAs($this->makeUser('stranger'));
        $this->patchJson("/api/v1/cat-hunter/cats/{$cat->id}/name", ['name' => 'Budi'], ['Idempotency-Key' => 'rn-2'])
            ->assertStatus(403);

        $fresh = $cat->fresh();
        $this->assertSame('Mochi', $fresh->name);
        $this->assertFalse($fresh->is_name_locked);
    }

    public function test_rename_success_locks_name_permanently(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);
        $this->meet($cat, 5);

        $url = "/api/v1/cat-hunter/cats/{$cat->id}/name";

        // Tanpa Idempotency-Key ditolak.
        $this->patchJson($url, ['name' => 'Budi'])->assertStatus(400);

        // Validasi: terlalu pendek, terlalu panjang, karakter terlarang.
        $this->patchJson($url, ['name' => 'B'], ['Idempotency-Key' => 'rs-1'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->patchJson($url, ['name' => str_repeat('a', 21)], ['Idempotency-Key' => 'rs-2'])->assertStatus(422);
        $this->patchJson($url, ['name' => '<b>Hi</b>'], ['Idempotency-Key' => 'rs-3'])->assertStatus(422);
        $this->patchJson($url, [], ['Idempotency-Key' => 'rs-4'])->assertStatus(422);
        $this->assertSame('Mochi', $cat->fresh()->name);

        // Sukses (spasi ganda dirapikan).
        $this->patchJson($url, ['name' => '  Si   Budi '], ['Idempotency-Key' => 'rs-5'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Si Budi')
            ->assertJsonPath('data.is_name_locked', true)
            ->assertJsonPath('data.can_rename', false);

        $fresh = $cat->fresh();
        $this->assertSame('Si Budi', $fresh->name);
        $this->assertTrue($fresh->is_name_locked);

        // Terkunci permanen: tidak bisa diganti lagi, oleh siapa pun.
        $this->patchJson($url, ['name' => 'Joko'], ['Idempotency-Key' => 'rs-6'])
            ->assertStatus(409)->assertJsonPath('code', 'NAME_ALREADY_LOCKED');
        $this->assertSame('Si Budi', $cat->fresh()->name);
    }

    public function test_rename_rejects_duplicate_name_case_insensitive(): void
    {
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        $this->makeCat('Boni', 'GKU', 'Putih');
        $deleted = $this->makeCat('Hantu');
        $deleted->delete();
        Sanctum::actingAs($user);
        $this->meet($cat, 5);

        $url = "/api/v1/cat-hunter/cats/{$cat->id}/name";

        $this->patchJson($url, ['name' => 'BONI'], ['Idempotency-Key' => 'dup-1'])
            ->assertStatus(422)->assertJsonPath('code', 'NAME_TAKEN')->assertJsonStructure(['errors' => ['name']]);
        $this->patchJson($url, ['name' => 'boni'], ['Idempotency-Key' => 'dup-2'])->assertStatus(422);
        $this->patchJson($url, ['name' => 'hantu'], ['Idempotency-Key' => 'dup-3'])->assertStatus(422);

        $fresh = $cat->fresh();
        $this->assertSame('Mochi', $fresh->name);
        $this->assertFalse($fresh->is_name_locked);

        // Nama unik tetap bisa dipakai setelah penolakan.
        $this->patchJson($url, ['name' => 'Unik'], ['Idempotency-Key' => 'dup-4'])->assertOk();
        $this->assertTrue($cat->fresh()->is_name_locked);

        // Nama yang sudah dikunci kini menjadi milik kucing ini: kucing lain tidak bisa memakainya.
        $third = $this->makeCat('Ketiga');
        $this->meet($third, 5, '2026-04-01');
        $this->patchJson("/api/v1/cat-hunter/cats/{$third->id}/name", ['name' => 'uNiK'], ['Idempotency-Key' => 'dup-5'])
            ->assertStatus(422)->assertJsonPath('code', 'NAME_TAKEN');
    }

    // ------------------------------------------------------------------
    // Profil & avatar
    // ------------------------------------------------------------------

    public function test_profile_stats_titles_best_buddy_medals_and_avatar(): void
    {
        $user = $this->makeUser();
        $a = $this->makeCat('Aa');
        $b = $this->makeCat('Bb', 'CACUK', 'Hitam');
        $this->makeCat('Cc', 'GKU', 'Putih'); // belum pernah ditemui
        Sanctum::actingAs($user);

        // Awal: Pendatang Baru, tanpa best buddy.
        $this->getJson('/api/v1/cat-hunter/profile')->assertOk()
            ->assertJsonPath('data.total_cats_collected', 0)
            ->assertJsonPath('data.total_encounters', 0)
            ->assertJsonPath('data.title', 'Pendatang Baru')
            ->assertJsonPath('data.best_buddy', null)
            ->assertJsonPath('data.favorite_avatar', null);

        $this->meet($a, 3);
        $this->meet($b, 1, '2026-03-20');

        // 4 pertemuan: masih Pendatang Baru.
        $this->getJson('/api/v1/cat-hunter/profile')->assertOk()
            ->assertJsonPath('data.title', 'Pendatang Baru')
            ->assertJsonPath('data.total_encounters', 4)
            ->assertJsonPath('data.total_cats_collected', 2);

        // 5 pertemuan: naik gelar; lalu 6 pertemuan dengan Aa = 5x.
        $this->at('2026-03-21 10:00:00');
        $this->encounter($a, 'p-4')->assertStatus(201);
        $this->getJson('/api/v1/cat-hunter/profile')->assertJsonPath('data.title', 'Sahabat Kucing Kampus');
        $this->at('2026-03-22 10:00:00');
        $this->encounter($a, 'p-5')->assertStatus(201);

        $res = $this->getJson('/api/v1/cat-hunter/profile')->assertOk()
            ->assertJsonPath('data.total_cats_collected', 2)
            ->assertJsonPath('data.total_catalog_cats', 3)
            ->assertJsonPath('data.total_encounters', 6)
            ->assertJsonPath('data.title', 'Sahabat Kucing Kampus')
            ->assertJsonPath('data.best_buddy.id', $a->id)
            ->assertJsonPath('data.best_buddy.encounter_count', 5);

        $medals = collect($res->json('data.medals'))->keyBy('id');
        $this->assertCount(6, $medals);
        $this->assertTrue($medals['medal_bestie']['is_unlocked']);
        $this->assertSame('Tercapai', $medals['medal_bestie']['progress_label']);
        $this->assertFalse($medals['medal_explorer']['is_unlocked']);
        $this->assertSame('2/3', $medals['medal_explorer']['progress_label']);
        $this->assertSame(2, $medals['medal_collector']['current']);
        $this->assertSame(3, $medals['medal_collector']['target']);

        // Gelar tertinggi di 15 pertemuan.
        $this->meet($a, 9, '2026-05-01');
        $this->getJson('/api/v1/cat-hunter/profile')
            ->assertJsonPath('data.total_encounters', 15)
            ->assertJsonPath('data.title', 'Pawang Kucing Tel-U');
    }

    public function test_set_favorite_avatar_rules(): void
    {
        $this->at('2026-03-10 08:00:00');
        $user = $this->makeUser();
        $a = $this->makeCat('Aa');
        $b = $this->makeCat('Bb');
        $uncollected = $this->makeCat('Cc');
        Sanctum::actingAs($user);
        $this->encounter($a, 'av-a')->assertStatus(201);
        $this->encounter($b, 'av-b')->assertStatus(201);

        $url = '/api/v1/cat-hunter/profile/avatar';

        $this->patchJson($url, ['cat_id' => $a->id])->assertStatus(400); // tanpa Idempotency-Key
        $this->patchJson($url, [], ['Idempotency-Key' => 'avs-1'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->patchJson($url, ['cat_id' => '01JNOTEXIST000000000000000'], ['Idempotency-Key' => 'avs-2'])->assertStatus(422);
        $this->patchJson($url, ['cat_id' => $uncollected->id], ['Idempotency-Key' => 'avs-3'])
            ->assertStatus(422)->assertJsonPath('code', 'CAT_NOT_COLLECTED');

        $this->patchJson($url, ['cat_id' => $a->id], ['Idempotency-Key' => 'avs-4'])
            ->assertOk()->assertJsonPath('data.id', $a->id)->assertJsonPath('data.is_favorite_avatar', true);
        $this->getJson('/api/v1/cat-hunter/profile')->assertJsonPath('data.favorite_avatar.id', $a->id);

        // Hanya satu avatar favorit pada satu waktu.
        $this->patchJson($url, ['cat_id' => $b->id], ['Idempotency-Key' => 'avs-5'])->assertOk();
        $this->assertSame(1, UserCatDex::where('user_id', $user->id)->where('is_favorite_avatar', true)->count());
        $this->getJson('/api/v1/cat-hunter/profile')->assertJsonPath('data.favorite_avatar.id', $b->id);

        $catalog = collect($this->getJson('/api/v1/cat-hunter/cats')->json('data'))->keyBy('id');
        $this->assertTrue($catalog[$b->id]['is_favorite_avatar']);
        $this->assertFalse($catalog[$a->id]['is_favorite_avatar']);
    }

    public function test_hunter_module_does_not_award_points(): void
    {
        $this->at('2026-03-10 08:00:00');
        $user = $this->makeUser();
        $cat = $this->makeCat('Mochi');
        Sanctum::actingAs($user);

        $before = DB::table('point_transactions')->count();
        $this->encounter($cat, 'np-1')->assertStatus(201);
        $this->assertSame($before, DB::table('point_transactions')->count());

        $payload = json_encode($this->getJson('/api/v1/cat-hunter/profile')->json());
        $this->assertStringNotContainsString('points', $payload);
    }
}
