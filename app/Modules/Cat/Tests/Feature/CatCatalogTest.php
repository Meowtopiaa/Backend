<?php

namespace App\Modules\Cat\Tests\Feature;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use App\Modules\Cat\Models\CatQrToken;
use App\Modules\Cat\Models\Territory;
use App\Modules\Core\Services\ScanResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\TestCase;

class CatCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['cat.edit', 'cat.curate', 'cat.qr.regenerate', 'spot.manage', 'feeding.review'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
    }

    private function makeUser(array $permissions = [], string $emailPrefix = 'user'): User
    {
        $user = User::create([
            'id' => (string) Str::ulid(),
            'name' => ucfirst($emailPrefix) . ' Tester',
            'email' => $emailPrefix . '-' . Str::lower(Str::random(6)) . '@telkomuniversity.ac.id',
            'password' => Hash::make('password'),
            'user_type' => 'STAF',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        foreach ($permissions as $perm) {
            $user->givePermissionTo(Permission::findByName($perm, 'web'));
        }

        return $user;
    }

    private function makeTerritory(string $code = 'TULT', string $name = 'Telkom University Landmark Tower'): Territory
    {
        return Territory::create([
            'id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name,
        ]);
    }

    public function test_admin_cat_crud_auto_qr_status_service_and_permissions(): void
    {
        $regularUser = $this->makeUser([], 'regular');
        $admin = $this->makeUser(['cat.edit', 'cat.curate', 'cat.qr.regenerate'], 'curator');
        $territory = $this->makeTerritory();

        // 1. Akses ditolak (403) untuk user tanpa izin cat.edit
        Sanctum::actingAs($regularUser);
        $this->getJson('/api/v1/admin/cats')->assertStatus(403);
        $this->postJson('/api/v1/admin/cats', ['name' => 'Mochi'], ['Idempotency-Key' => 'cat-unauth-1'])
            ->assertStatus(403);

        // 2. Admin tanpa Idempotency-Key ditolak
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/admin/cats', ['name' => 'Mochi'])
            ->assertStatus(400);

        // 3. Validasi gagal (422) saat nama kosong / enum salah
        $this->postJson('/api/v1/admin/cats', [
            'name' => '',
            'gender' => 'INVALID_GENDER',
        ], ['Idempotency-Key' => 'cat-invalid-1'])
            ->assertStatus(422);

        // 4. Create kucing sukses dengan seluruh kebutuhan Enigma, Game, Rekans Caps, Optimind
        $createRes = $this->postJson('/api/v1/admin/cats', [
            'name' => 'Mochi',
            'gender' => 'FEMALE',
            'primary_color' => 'Oranye Putih',
            'fur_pattern' => 'Calico',
            'age_group' => 'ADULT',
            'is_sterilized' => true,
            'is_vaccinated' => true,
            'vaccinated_at' => '2026-10-01',
            'vaccination_notes' => 'F3 & Rabies',
            'temperament' => 'Ramah dan tenang',
            'special_needs' => 'Sensitif ikan laut',
            'description' => 'Sering tidur di lobi TULT.',
            'traits' => [
                'physical_marks' => 'Bercak oranye di telinga kiri',
                'ear_tip' => 'Ear-tip kiri',
                'tail_type' => 'Panjang lurus',
            ],
            'territory_id' => $territory->id,
            'health_status' => 'HEALTHY',
            'adoption_status' => 'READY',
            'status_reason' => 'Sudah lolos pemeriksaan kesehatan awal',
        ], ['Idempotency-Key' => 'cat-create-mochi-1']);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Mochi')
            ->assertJsonPath('data.primary_color', 'Oranye Putih')
            ->assertJsonPath('data.fur_pattern', 'Calico')
            ->assertJsonPath('data.color_pattern', 'Oranye Putih Calico')
            ->assertJsonPath('data.is_sterilized', true)
            ->assertJsonPath('data.is_vaccinated', true)
            ->assertJsonPath('data.temperament', 'Ramah dan tenang')
            ->assertJsonPath('data.special_needs', 'Sensitif ikan laut')
            ->assertJsonPath('data.adoption_status', 'READY')
            ->assertJsonPath('data.qr_status', 'ACTIVE');

        $catId = $createRes->json('data.id');
        $qrPayload = (string) $createRes->json('data.qr_payload');
        $this->assertStringStartsWith('MTP1.C.', $qrPayload);

        // Pastikan status READY tercatat via CatStatusService di cat_status_logs
        $this->assertDatabaseHas('cat_status_logs', [
            'cat_id' => $catId,
            'dimension' => 'adoption_status',
            'from_value' => 'UNREVIEWED',
            'to_value' => 'READY',
        ]);

        // Pastikan audit_logs tidak membocorkan token mentah
        $rawToken = substr($qrPayload, strlen('MTP1.C.'));
        $auditReasons = DB::table('audit_logs')->where('subject_id', $catId)->pluck('reason')->implode(' ');
        $this->assertStringNotContainsString($rawToken, $auditReasons);

        // 5. Update profil + perubahan health_status lewat CatStatusService
        $this->putJson("/api/v1/admin/cats/{$catId}", [
            'temperament' => 'Sangat manja',
            'health_status' => 'UNDER_CARE',
            'status_reason' => 'Observasi flu ringan oleh relawan',
        ])->assertOk()
            ->assertJsonPath('data.temperament', 'Sangat manja')
            ->assertJsonPath('data.health_status', 'UNDER_CARE');

        $this->assertDatabaseHas('cat_status_logs', [
            'cat_id' => $catId,
            'dimension' => 'health_status',
            'from_value' => 'HEALTHY',
            'to_value' => 'UNDER_CARE',
        ]);

        // Transisi status tidak valid -> 409 CONFLICT
        $this->putJson("/api/v1/admin/cats/{$catId}", [
            'adoption_status' => 'RETURNED_REVIEW',
        ])->assertStatus(409)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');

        // 6. Soft delete tanpa menghapus riwayat & restore kembali
        $this->deleteJson("/api/v1/admin/cats/{$catId}", ['reason' => 'Duplikat sementara'])
            ->assertOk();
        $this->assertSoftDeleted('cats', ['id' => $catId]);
        $this->assertDatabaseHas('cat_status_logs', ['cat_id' => $catId]);

        $this->postJson("/api/v1/admin/cats/{$catId}/restore")
            ->assertOk()
            ->assertJsonPath('data.deleted_at', null);
    }

    public function test_public_catalog_filters_game_rarity_and_hides_raw_qr_tokens(): void
    {
        $admin = $this->makeUser(['cat.edit'], 'adminpub');
        $viewer = $this->makeUser([], 'viewer');
        $territory = $this->makeTerritory('GKU', 'Gedung Tokong Nanas');

        Sanctum::actingAs($admin);
        $mochiId = $this->postJson('/api/v1/admin/cats', [
            'name' => 'Mochi',
            'gender' => 'FEMALE',
            'primary_color' => 'Oranye',
            'fur_pattern' => 'Calico',
            'is_sterilized' => true,
            'is_vaccinated' => true,
            'territory_id' => $territory->id,
            'adoption_status' => 'READY',
        ], ['Idempotency-Key' => 'pub-1'])->json('data.id');

        $kuroId = $this->postJson('/api/v1/admin/cats', [
            'name' => 'Kuro',
            'gender' => 'MALE',
            'primary_color' => 'Hitam',
            'fur_pattern' => 'Polos',
            'is_sterilized' => false,
            'is_vaccinated' => false,
            'health_status' => 'SICK',
        ], ['Idempotency-Key' => 'pub-2'])->json('data.id');

        // Simulasikan data rarity di tabel satelit game_cat_profiles milik CatHunter
        DB::table('game_cat_profiles')->insert([
            'cat_id' => $mochiId,
            'rarity' => 'EPIC',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($viewer);

        // Filter is_vaccinated=1 & adoption_status=READY hanya mengembalikan Mochi
        $listRes = $this->getJson('/api/v1/cats?is_vaccinated=1&adoption_status=READY')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mochiId)
            ->assertJsonPath('data.0.game_rarity', 'EPIC')
            ->assertJsonPath('data.0.territory.code', 'GKU');

        // Respons publik TIDAK boleh membocorkan qr_payload mentah
        $this->assertArrayNotHasKey('qr_payload', $listRes->json('data.0'));

        // Filter health_status=SICK (kebutuhan Optimind) mengembalikan Kuro
        $this->getJson('/api/v1/cats?health_status=SICK')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $kuroId);

        // Detail & by-code publik bekerja dan tidak membocorkan qr_payload
        $uniqueCode = Cat::findOrFail($mochiId)->unique_code;
        $detailRes = $this->getJson("/api/v1/cats/by-code/" . strtolower($uniqueCode))
            ->assertOk()
            ->assertJsonPath('data.id', $mochiId);
        $this->assertArrayNotHasKey('qr_payload', $detailRes->json('data'));
    }

    public function test_photo_upload_and_public_photo_stream(): void
    {
        Storage::fake('local');
        config(['filesystems.default' => 'local']);

        $admin = $this->makeUser(['cat.edit'], 'photoadmin');
        Sanctum::actingAs($admin);

        $catId = $this->postJson('/api/v1/admin/cats', [
            'name' => 'Oyen',
        ], ['Idempotency-Key' => 'photo-cat-1'])->json('data.id');

        // Minimal valid 1x1 PNG binary (tanpa ketergantungan ekstensi GD)
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=');
        $fakePhoto = UploadedFile::fake()->createWithContent('oyen.png', $pngBytes);

        $uploadRes = $this->post("/api/v1/admin/cats/{$catId}/photos", [
            'photo' => $fakePhoto,
            'angle' => 'front',
            'make_primary' => true,
        ], ['Accept' => 'application/json']);

        $uploadRes->assertStatus(201)
            ->assertJsonPath('data.angle', 'front');

        $mediaId = $uploadRes->json('data.media_id');
        $this->assertDatabaseHas('cat_photos', [
            'cat_id' => $catId,
            'media_id' => $mediaId,
        ]);

        // Stream foto publik berjalan normal
        $this->get("/api/v1/cats/{$catId}/photos/{$mediaId}")
            ->assertOk();
    }

    public function test_qr_regeneration_revokes_old_token_syncs_scan_resolver_and_renders_acrylic_svg(): void
    {
        $editorOnly = $this->makeUser(['cat.edit'], 'editoronly');
        $qrAdmin = $this->makeUser(['cat.edit', 'cat.qr.regenerate'], 'qradmin');

        Sanctum::actingAs($qrAdmin);
        $createRes = $this->postJson('/api/v1/admin/cats', [
            'name' => 'Mochi',
            'unique_code' => 'CAT777',
        ], ['Idempotency-Key' => 'qr-cat-1'])->assertStatus(201);

        $catId = $createRes->json('data.id');
        $oldPayload = $createRes->json('data.qr_payload');
        $oldToken = substr($oldPayload, strlen('MTP1.C.'));

        // User tanpa izin cat.qr.regenerate ditolak (403)
        Sanctum::actingAs($editorOnly);
        $this->postJson("/api/v1/admin/cats/{$catId}/qr/regenerate", ['reason' => 'Hilang'])
            ->assertStatus(403);

        // User berizin cat.qr.regenerate berhasil mencabut token lama & terbitkan token baru
        Sanctum::actingAs($qrAdmin);
        $regenRes = $this->postJson("/api/v1/admin/cats/{$catId}/qr/regenerate", [
            'reason' => 'Kalung lama rusak tergores',
        ])->assertOk();

        $newPayload = $regenRes->json('data.qr_payload');
        $newToken = substr($newPayload, strlen('MTP1.C.'));
        $this->assertNotSame($oldPayload, $newPayload);

        $this->assertDatabaseHas('cat_qr_tokens', [
            'cat_id' => $catId,
            'token' => $oldToken,
            'status' => 'REVOKED',
        ]);
        $this->assertDatabaseHas('cat_qr_tokens', [
            'cat_id' => $catId,
            'token' => $newToken,
            'status' => 'ACTIVE',
        ]);

        // Audit log tidak menyimpan raw token lama/baru
        $auditReasons = DB::table('audit_logs')->where('subject_id', $catId)->pluck('reason')->implode(' ');
        $this->assertStringNotContainsString($oldToken, $auditReasons);
        $this->assertStringNotContainsString($newToken, $auditReasons);

        // ScanResolver menerima token baru & manual code lowercase, menolak token lama dengan QR_REVOKED
        $resolver = app(ScanResolver::class);
        $resolvedNew = $resolver->resolve($newPayload);
        $this->assertSame('cat', $resolvedNew['type']);
        $this->assertSame($catId, $resolvedNew['id']);

        $resolvedManual = $resolver->resolve('cat777');
        $this->assertSame($catId, $resolvedManual['id']);
        $this->assertTrue($resolvedManual['is_manual']);

        try {
            $resolver->resolve($oldPayload);
            $this->fail('Token QR lama yang REVOKED seharusnya ditolak oleh ScanResolver.');
        } catch (BadRequestHttpException $e) {
            $this->assertStringContainsString('QR_REVOKED', $e->getMessage());
        }

        // Download Tag Akrilik Depan, Belakang, SVG, & PNG
        $frontSvg = $this->get("/api/v1/admin/cats/{$catId}/qr-download?format=acrylic_front")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->getContent();
        $this->assertStringContainsString('<svg', $frontSvg);
        $this->assertStringContainsString('Mochi', $frontSvg);
        $this->assertStringContainsString('CAT777', $frontSvg);

        $backSvg = $this->get("/api/v1/admin/cats/{$catId}/qr-download?format=acrylic_back")
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Meowtopia', $backSvg);

        $pngBin = $this->get("/api/v1/admin/cats/{$catId}/qr-download?format=png")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->getContent();
        $this->assertStringStartsWith("\x89PNG", $pngBin);
    }

    public function test_unified_admin_web_panel_crud_and_acrylic_qr_print_sheet(): void
    {
        $regularUser = $this->makeUser([], 'webregular');
        $superAdmin = $this->makeUser(['cat.edit', 'cat.curate', 'cat.qr.regenerate', 'spot.manage', 'feeding.review'], 'websuper');

        // User biasa ditolak (403)
        $this->actingAs($regularUser, 'web')
            ->get('/admin/cats')
            ->assertStatus(403);

        // Super admin mengakses 1 portal terpadu (/admin/cats & /admin/feeding saling terhubung)
        $this->actingAs($superAdmin, 'web')
            ->get('/admin/cats')
            ->assertOk()
            ->assertSee('Katalog Kucing')
            ->assertSee('Lokasi Feeding Spot');

        // Tambah kucing via form web admin
        $this->actingAs($superAdmin, 'web')
            ->post('/admin/cats', [
                'name' => 'Comel',
                'gender' => 'FEMALE',
                'age_group' => 'KITTEN',
                'primary_color' => 'Putih Oranye',
                'fur_pattern' => 'Calico',
                'is_sterilized' => '0',
                'is_vaccinated' => '1',
                'temperament' => 'Aktif dan ceria',
                'presence_status' => 'ON_CAMPUS',
                'health_status' => 'HEALTHY',
                'adoption_status' => 'READY',
            ])
            ->assertRedirect();

        $cat = Cat::where('name', 'Comel')->firstOrFail();
        $this->assertNotNull(CatQrToken::where('cat_id', $cat->id)->where('status', 'ACTIVE')->first());

        // Halaman katalog menampilkan tombol Detail + pagination 10 per halaman + search by ID
        for ($i = 1; $i <= 11; $i++) {
            Cat::create([
                'unique_code' => 'PAG' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'name' => 'Kucing Page ' . $i,
                'gender' => 'MALE',
                'age_group' => 'ADULT',
                'presence_status' => 'ON_CAMPUS',
                'health_status' => 'HEALTHY',
                'adoption_status' => 'UNREVIEWED',
            ]);
        }

        $this->actingAs($superAdmin, 'web')
            ->get('/admin/cats')
            ->assertOk()
            ->assertSee('Detail')
            ->assertSee('10 per halaman')
            ->assertSee('Berikutnya');

        // Search berdasarkan ULID id kucing
        $this->actingAs($superAdmin, 'web')
            ->get('/admin/cats?search=' . $cat->id)
            ->assertOk()
            ->assertSee('Comel');

        // Halaman cetak Tag QR Akrilik Depan-Belakang siap print
        $this->actingAs($superAdmin, 'web')
            ->get("/admin/cats/{$cat->id}/qr-sheet")
            ->assertOk()
            ->assertSee('Comel')
            ->assertSee($cat->unique_code)
            ->assertSee('42')
            ->assertSee('Sisi Depan')
            ->assertSee('Sisi Belakang');

        $this->actingAs($superAdmin, 'web')
            ->get('/admin/cats/qr-sheet')
            ->assertOk()
            ->assertSee('Comel');
    }
}
