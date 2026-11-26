<?php

namespace App\Modules\IncidentReport\Tests\Feature;

use App\Modules\IncidentReport\Models\CaretakerProfile;
use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Models\VetContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Optimind EIR-12, EIR-13, CRUD /admin/vets, CRUD /admin/caretakers, GET /admin/incidents/hotspots.
 */
class IncidentAdminDataApiTest extends TestCase
{
    use RefreshDatabase;
    use IncidentTestHelpers;

    public function test_admin_manages_vet_contacts_and_users_see_only_active(): void
    {
        $admin = $this->incidentAdmin();
        $this->actingAsUser($admin);

        $vetId = $this->postJson('/api/v1/admin/vets', [
            'name' => 'Klinik Mitra Uji',
            'address' => 'Jl. Contoh No. 1',
            'phone' => '022-1234567',
            'whatsapp' => '+628123456789',
            'location' => ['lat' => -6.97, 'lng' => 107.63],
            'open_hours' => '08.00-17.00',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Klinik Mitra Uji')
            ->assertJsonPath('data.location.lat', -6.97)
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->postJson('/api/v1/admin/vets', ['phone' => 'bukan-nomor'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['name', 'phone']]);

        $this->patchJson("/api/v1/admin/vets/{$vetId}", ['open_hours' => '24 jam'])
            ->assertOk()
            ->assertJsonPath('data.open_hours', '24 jam');

        $this->actingAsUser($this->reporter());
        $this->getJson('/api/v1/vets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.whatsapp', '+628123456789');
        $this->postJson('/api/v1/admin/vets', ['name' => 'Palsu'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/vets/{$vetId}")->assertForbidden();

        $this->actingAsUser($admin);
        $this->patchJson("/api/v1/admin/vets/{$vetId}", ['is_active' => false])->assertOk();
        $this->actingAsUser($this->reporter());
        $this->getJson('/api/v1/vets')->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsUser($admin);
        $this->deleteJson("/api/v1/admin/vets/{$vetId}")->assertOk();
        $this->assertSame(0, VetContact::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.vet_deleted', 'subject_id' => $vetId]);
    }

    public function test_admin_registers_caretakers_without_assigning_roles(): void
    {
        $admin = $this->incidentAdmin();
        $volunteer = $this->reporter();
        $territoryId = (string) Str::ulid();
        DB::table('territories')->insert(['id' => $territoryId, 'code' => 'TULT', 'name' => 'TULT', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAsUser($volunteer);
        $this->getJson('/api/v1/caretaker/tasks')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->postJson('/api/v1/admin/caretakers', ['user_id' => $volunteer->id])->assertForbidden();

        $this->actingAsUser($admin);
        $this->postJson('/api/v1/admin/caretakers', [
            'user_id' => $volunteer->id,
            'phone' => '0811111111',
            'territory_ids' => [$territoryId],
            'last_location' => ['lat' => -6.9745, 'lng' => 107.6318],
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $volunteer->id)
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.territory_ids.0', $territoryId);

        $this->postJson('/api/v1/admin/caretakers', ['user_id' => $volunteer->id])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['user_id']]);
        $this->postJson('/api/v1/admin/caretakers', ['user_id' => $this->reporter()->id, 'territory_ids' => [(string) Str::ulid()]])
            ->assertStatus(422);

        $this->assertFalse($volunteer->fresh()->hasRole('caretaker'));

        $this->patchJson("/api/v1/admin/caretakers/{$volunteer->id}", ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);
        $this->getJson('/api/v1/admin/caretakers?available=0')->assertOk()->assertJsonCount(1, 'data');

        // Relawan yang tidak tersedia hanya bisa ditugaskan dengan override beralasan.
        $ticket = $this->createIncident($this->reporter());
        $this->actingAsUser($admin);
        $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $volunteer->id])->assertStatus(409);
        $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $volunteer->id, 'override' => true, 'reason' => 'Satu-satunya relawan di area.'])
            ->assertOk();

        // Tidak dapat dihapus selama punya tugas aktif.
        $this->deleteJson("/api/v1/admin/caretakers/{$volunteer->id}")->assertStatus(409);
        IncidentAssignment::query()->update(['status' => IncidentAssignment::STATUS_DECLINED]);
        $this->deleteJson("/api/v1/admin/caretakers/{$volunteer->id}")->assertOk();
        $this->assertNull(CaretakerProfile::find($volunteer->id));
        $this->assertSame(1, IncidentAssignment::count());
    }

    public function test_candidates_rank_available_nearby_caretakers_first(): void
    {
        $ticket = $this->createIncident($this->reporter(), ['location' => ['lat' => -6.97452, 'lng' => 107.63180]]);
        $near = $this->caretaker(['last_location_lat' => -6.9746, 'last_location_lng' => 107.6319]);
        $far = $this->caretaker(['last_location_lat' => -6.9900, 'last_location_lng' => 107.6500]);
        $busy = $this->caretaker(['is_available' => false]);

        $this->actingAsUser($this->incidentAdmin());
        $candidates = $this->getJson("/api/v1/admin/incidents/{$ticket['id']}/candidates")->assertOk()->json('data.candidates');

        $this->assertSame([$near->id, $far->id, $busy->id], array_column($candidates, 'caretaker_id'));
        $this->assertLessThan(50, $candidates[0]['distance_m']);
        $this->assertGreaterThan(1000, $candidates[1]['distance_m']);
    }

    public function test_hotspots_aggregate_by_grid_for_admin_only(): void
    {
        $reporter = $this->reporter();
        $this->createIncident($reporter, ['location' => ['lat' => -6.97452, 'lng' => 107.63180]]);
        $this->createIncident($reporter, ['category' => 'SICK', 'location' => ['lat' => -6.97455, 'lng' => 107.63185]]);
        $this->createIncident($reporter, ['location' => ['lat' => -6.96930, 'lng' => 107.62815]]);

        $this->getJson('/api/v1/admin/incidents/hotspots')->assertForbidden();

        $this->actingAsUser($this->incidentAdmin());
        $this->getJson('/api/v1/admin/incidents/hotspots?days=7')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.count', 2)
            ->assertJsonPath('data.0.open_count', 2)
            ->assertJsonPath('data.0.categories.SICK', 1)
            ->assertJsonPath('data.0.categories.INJURED', 1)
            ->assertJsonPath('meta.total_incidents', 3);

        $this->getJson('/api/v1/admin/incidents/hotspots?category=SICK')->assertOk()->assertJsonCount(1, 'data');
    }
}
