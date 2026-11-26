<?php

namespace App\Modules\IncidentReport\Tests\Feature;

use App\Models\User;
use App\Modules\Cat\Models\Cat;
use App\Modules\IncidentReport\Models\CaretakerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

trait IncidentTestHelpers
{
    protected function reporter(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['phone' => '081234567890']);
    }

    protected function incidentAdmin(array $permissions = ['incident.view', 'incident.dispatch', 'incident.close']): User
    {
        $admin = User::factory()->create(['name' => 'Admin Insiden Uji']);
        foreach ($permissions as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $admin;
    }

    /** Relawan: profil caretaker + permission incident.view seperti role caretaker di seeder. */
    protected function caretaker(array $profile = []): User
    {
        $user = User::factory()->create(['name' => 'Relawan Uji']);
        $user->givePermissionTo(Permission::findOrCreate('incident.view', 'web'));
        CaretakerProfile::create($profile + ['user_id' => $user->id, 'is_available' => true, 'phone' => '089900001111']);

        return $user;
    }

    protected function cat(array $attributes = []): Cat
    {
        $cat = Cat::create(['unique_code' => 'TST-' . Str::upper(Str::random(6)), 'name' => 'Oyen']);
        $cat->forceFill($attributes)->save();

        return $cat->fresh();
    }

    protected function media(User $owner, string $purpose = 'incident_media'): string
    {
        $id = (string) Str::ulid();
        DB::table('media_files')->insert([
            'id' => $id,
            'owner_id' => $owner->id,
            'disk' => 'local',
            'path' => "media/{$purpose}/{$id}.jpg",
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'sha256' => hash('sha256', $id),
            'purpose' => $purpose,
            'visibility' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    protected function seedIncidentPointRule(): void
    {
        DB::table('point_rules')->insert([
            'id' => (string) Str::ulid(),
            'rule_key' => 'incident.report',
            'module' => 'IncidentReport',
            'points' => 20,
            'daily_cap_count' => 3,
            'daily_cap_points' => 60,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function payload(array $overrides = []): array
    {
        return array_replace([
            'category' => 'INJURED',
            'urgency' => 'HIGH',
            'description' => 'Kucing oranye pincang kaki depan kiri dekat parkiran.',
            'location' => ['lat' => -6.97452, 'lng' => 107.63180, 'accuracy_m' => 12],
            'location_note' => 'Parkiran motor Gedung Selaru',
            'media_ids' => [],
            'source' => 'DIRECT',
        ], $overrides);
    }

    protected function key(): array
    {
        return ['Idempotency-Key' => (string) Str::uuid()];
    }

    protected function createIncident(User $reporter, array $overrides = []): array
    {
        $this->actingAsUser($reporter);

        return $this->postJson('/api/v1/incidents', $this->payload($overrides), $this->key())
            ->assertCreated()
            ->json('data');
    }

    protected function actingAsUser(User $user): void
    {
        app('auth')->forgetGuards();
        \Laravel\Sanctum\Sanctum::actingAs($user);
    }
}
