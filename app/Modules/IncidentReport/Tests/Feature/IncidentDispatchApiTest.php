<?php

namespace App\Modules\IncidentReport\Tests\Feature;

use App\Modules\IncidentReport\Models\IncidentAssignment;
use App\Modules\IncidentReport\Models\IncidentReport;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Optimind EIR-05..09, EIR-11, IT-10, IT-15: antrean admin, verifikasi, penugasan, dan status relawan.
 */
class IncidentDispatchApiTest extends TestCase
{
    use RefreshDatabase;
    use IncidentTestHelpers;

    public function test_full_flow_from_report_to_close_updates_cat_health_and_points_once(): void
    {
        $this->seedIncidentPointRule();
        $reporter = $this->reporter();
        $admin = $this->incidentAdmin();
        $caretaker = $this->caretaker();
        $cat = $this->cat();
        $ticket = $this->createIncident($reporter, ['cat_id' => $cat->id, 'category' => 'SICK', 'media_ids' => [$this->media($reporter)]]);
        $id = $ticket['id'];

        // Admin memverifikasi: health_status SICK dan poin incident.report sekali.
        $this->actingAsUser($admin);
        $this->postJson("/api/v1/admin/incidents/{$id}/verify", ['note' => 'Laporan valid.'])
            ->assertOk()
            ->assertJsonPath('data.incident.status', 'WAITING_ASSIGNMENT')
            ->assertJsonPath('data.reward.status', 'CREDITED')
            ->assertJsonPath('data.reward.points', 20);
        $this->assertSame('SICK', $cat->fresh()->health_status);
        $this->assertDatabaseHas('cat_status_logs', ['cat_id' => $cat->id, 'to_value' => 'SICK', 'source_module' => 'IncidentReport']);

        $this->postJson("/api/v1/admin/incidents/{$id}/verify")->assertOk()->assertJsonPath('data.reward', null);
        $this->assertSame(1, DB::table('point_transactions')->where('rule_key', 'incident.report')->count());

        // Admin menetapkan relawan: tawaran dan notifikasi in-app tercatat sesuai kenyataan.
        $this->postJson("/api/v1/admin/incidents/{$id}/assign", ['caretaker_id' => $caretaker->id])
            ->assertOk()
            ->assertJsonPath('data.assignment.status', 'OFFERED')
            ->assertJsonPath('data.incident.notification_status', 'SENT')
            ->assertJsonPath('data.incident.has_assignee', false);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $caretaker->id)->where('status', 'SENT')->count());

        // Relawan melihat tawaran (dengan lokasi untuk memutuskan), lalu menerima.
        $this->actingAsUser($caretaker);
        $tasks = $this->getJson('/api/v1/caretaker/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'OFFERED')
            ->assertJsonPath('data.0.incident.id', $id)
            ->assertJsonPath('data.0.incident.location.lat', -6.97452);
        $this->assertArrayNotHasKey('reporter', $tasks->json('data.0.incident'));
        $this->assertStringNotContainsString('081234567890', $tasks->getContent());
        $taskId = $tasks->json('data.0.id');

        $this->postJson("/api/v1/caretaker/tasks/{$taskId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACCEPTED')
            ->assertJsonPath('data.incident.status', 'ASSIGNED')
            ->assertJsonPath('data.incident.has_assignee', true);

        // Relawan tidak boleh melompati IN_PROGRESS.
        $this->postJson("/api/v1/incidents/{$id}/status", ['status' => 'RESOLVED', 'note' => 'Selesai'], $this->key())
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');

        $this->postJson("/api/v1/incidents/{$id}/status", ['status' => 'IN_PROGRESS', 'note' => 'Menuju lokasi.'], $this->key())
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->postJson("/api/v1/incidents/{$id}/status", ['status' => 'RESOLVED', 'note' => 'Dibawa ke klinik dan diberi obat.'], $this->key())
            ->assertOk()
            ->assertJsonPath('data.status', 'RESOLVED')
            ->assertJsonPath('data.resolution_note', 'Dibawa ke klinik dan diberi obat.');
        $this->assertSame('UNDER_CARE', $cat->fresh()->health_status);
        $this->assertSame(IncidentAssignment::STATUS_DONE, IncidentAssignment::find($taskId)->status);

        // Relawan tidak dapat menutup tiket (tugasnya sudah DONE).
        $this->postJson("/api/v1/incidents/{$id}/status", ['status' => 'CLOSED', 'note' => 'Tutup'], $this->key())
            ->assertForbidden();

        // Pelapor melihat riwayat lengkap dengan label peran, bukan nama petugas.
        $this->actingAsUser($reporter);
        $detail = $this->getJson("/api/v1/incidents/{$id}")->assertOk();
        $this->assertSame(
            ['RECEIVED', 'WAITING_ASSIGNMENT', 'ASSIGNED', 'IN_PROGRESS', 'RESOLVED'],
            array_column($detail->json('data.status_logs'), 'to_status')
        );
        $this->assertSame(['Pelapor', 'Admin Insiden', 'Relawan', 'Relawan', 'Relawan'], array_column(array_column($detail->json('data.status_logs'), 'actor'), 'name'));
        $this->assertStringNotContainsString('Relawan Uji', $detail->getContent());
        $this->assertStringNotContainsString('Admin Insiden Uji', $detail->getContent());

        // Admin menutup setelah peninjauan dan menyatakan kucing sehat.
        $this->actingAsUser($admin);
        $this->postJson("/api/v1/admin/incidents/{$id}/close", ['note' => 'Ditinjau, kucing pulih.', 'cat_health_status' => 'HEALTHY'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CLOSED')
            ->assertJsonPath('data.reporter.phone', '081234567890');
        $this->assertSame('HEALTHY', $cat->fresh()->health_status);
        $this->assertNotNull(IncidentReport::find($id)->closed_at);
        $this->assertSame(1, DB::table('point_transactions')->where('rule_key', 'incident.report')->count());
    }

    public function test_admin_queue_requires_dispatch_permission_and_hides_contacts_in_list(): void
    {
        $reporter = $this->reporter();
        $low = $this->createIncident($reporter, ['urgency' => 'LOW', 'category' => 'OTHER']);
        $high = $this->createIncident($reporter, ['urgency' => 'HIGH']);

        // Relawan hanya punya incident.view (seperti seeder) dan tidak boleh melihat semua laporan.
        $this->actingAsUser($this->caretaker());
        $this->getJson('/api/v1/admin/incidents')->assertForbidden();

        $this->actingAsUser($this->incidentAdmin(['incident.view']));
        $this->getJson('/api/v1/admin/incidents')->assertForbidden();

        $this->actingAsUser($this->incidentAdmin());
        $list = $this->getJson('/api/v1/admin/incidents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $high['id'])
            ->assertJsonPath('data.1.id', $low['id'])
            ->assertJsonPath('data.0.reporter.name', $reporter->name);
        $this->assertStringNotContainsString('081234567890', $list->getContent());
        $this->assertArrayNotHasKey('media', $list->json('data.0'));

        $this->getJson('/api/v1/admin/incidents?urgency=LOW')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $low['id']);
        $this->getJson('/api/v1/admin/incidents?status=RESOLVED')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/admin/incidents?category=OTHER,INJURED')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/admin/incidents/{$high['id']}")
            ->assertOk()
            ->assertJsonPath('data.reporter.phone', '081234567890')
            ->assertJsonPath('data.status_logs.0.actor.name', $reporter->name);
    }

    public function test_ticket_without_caretaker_stays_in_queue_with_accurate_status(): void
    {
        // IT-10: tiket tanpa cat_id dan tanpa relawan.
        $ticket = $this->createIncident($this->reporter(), ['category' => 'OTHER']);

        $this->actingAsUser($this->incidentAdmin());
        $this->getJson("/api/v1/admin/incidents/{$ticket['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.has_assignee', false)
            ->assertJsonPath('data.notification_status', 'NOT_REQUESTED')
            ->assertJsonPath('data.active_assignment', null);

        $this->getJson("/api/v1/admin/incidents/{$ticket['id']}/candidates")
            ->assertOk()
            ->assertJsonCount(0, 'data.candidates');
    }

    public function test_decline_and_timeout_return_ticket_to_waiting_assignment(): void
    {
        $admin = $this->incidentAdmin();
        $first = $this->caretaker();
        $second = $this->caretaker();
        $ticket = $this->createIncident($this->reporter());

        $this->actingAsUser($admin);
        $offer = $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $first->id])->assertOk();
        $offerId = $offer->json('data.assignment.id');

        $this->actingAsUser($first);
        $this->postJson("/api/v1/caretaker/tasks/{$offerId}/decline", ['reason' => 'Sedang di luar kampus.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'DECLINED');
        $this->assertSame('WAITING_ASSIGNMENT', IncidentReport::find($ticket['id'])->status);
        $this->postJson("/api/v1/caretaker/tasks/{$offerId}/accept")->assertStatus(409);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.task_declined', 'subject_id' => $offerId]);

        // Tawaran kedua tanpa respons 15 menit.
        $this->actingAsUser($admin);
        $secondOffer = $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $second->id])->assertOk()->json('data.assignment.id');

        $this->travel(16)->minutes();

        $this->actingAsUser($second);
        $this->getJson('/api/v1/caretaker/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/caretaker/tasks/{$secondOffer}/accept")->assertStatus(409)->assertJsonPath('code', 'CONFLICT');

        $expired = IncidentAssignment::find($secondOffer);
        $this->assertSame([IncidentAssignment::STATUS_DECLINED, 'TIMEOUT'], [$expired->status, $expired->ended_reason]);
        $this->assertSame('WAITING_ASSIGNMENT', IncidentReport::find($ticket['id'])->status);

        // Admin melihat kandidat dengan riwayat penolakan.
        $this->actingAsUser($admin);
        $candidates = $this->getJson("/api/v1/admin/incidents/{$ticket['id']}/candidates")->assertOk()->json('data.candidates');
        $this->assertCount(2, $candidates);
        $this->assertTrue(collect($candidates)->firstWhere('caretaker_id', $first->id)['declined_before']);
    }

    public function test_expire_command_processes_stale_offers(): void
    {
        $ticket = $this->createIncident($this->reporter());
        $caretaker = $this->caretaker();
        $this->actingAsUser($this->incidentAdmin());
        $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $caretaker->id])->assertOk();

        $this->travel(20)->minutes();
        $this->artisan('incident:expire-offers')->assertSuccessful();

        $this->assertSame(0, IncidentAssignment::whereIn('status', IncidentAssignment::ACTIVE_STATUSES)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.task_timeout']);
    }

    public function test_only_one_active_assignee_without_logged_override(): void
    {
        $admin = $this->incidentAdmin();
        $first = $this->caretaker();
        $second = $this->caretaker();
        $ticket = $this->createIncident($this->reporter());
        $id = $ticket['id'];

        $this->actingAsUser($admin);
        $this->postJson("/api/v1/admin/incidents/{$id}/assign", ['caretaker_id' => $first->id])->assertOk();
        // Penugasan ulang ke relawan yang sama idempoten.
        $this->postJson("/api/v1/admin/incidents/{$id}/assign", ['caretaker_id' => $first->id])->assertOk();

        // IT-15: admin kedua tanpa override ditolak dan konflik dicatat.
        $this->postJson("/api/v1/admin/incidents/{$id}/assign", ['caretaker_id' => $second->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.assign', 'subject_id' => $id, 'result' => 'CONFLICT']);

        $this->postJson("/api/v1/admin/incidents/{$id}/assign", ['caretaker_id' => $second->id, 'override' => true])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['reason']]);

        $this->postJson("/api/v1/admin/incidents/{$id}/assign", ['caretaker_id' => $second->id, 'override' => true, 'reason' => 'Relawan pertama tidak dapat dihubungi.'])
            ->assertOk()
            ->assertJsonPath('data.assignment.caretaker.id', $second->id);

        $this->assertSame(1, IncidentAssignment::where('incident_id', $id)->whereIn('status', IncidentAssignment::ACTIVE_STATUSES)->count());
        $this->assertSame(IncidentAssignment::STATUS_CANCELLED, IncidentAssignment::where('caretaker_id', $first->id)->sole()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.assign_override', 'subject_id' => $id]);

        // Relawan yang dialihkan tidak lagi melihat tiket.
        $this->actingAsUser($first);
        $this->getJson('/api/v1/caretaker/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/incidents/{$id}")->assertNotFound();
    }

    public function test_database_rejects_second_active_assignment(): void
    {
        $admin = $this->incidentAdmin();
        $ticket = $this->createIncident($this->reporter());
        $a = $this->caretaker();
        $b = $this->caretaker();

        IncidentAssignment::create(['incident_id' => $ticket['id'], 'caretaker_id' => $a->id, 'assigned_by' => $admin->id, 'status' => 'ACCEPTED']);

        $this->expectException(QueryException::class);
        IncidentAssignment::create(['incident_id' => $ticket['id'], 'caretaker_id' => $b->id, 'assigned_by' => $admin->id, 'status' => 'OFFERED']);
    }

    public function test_caretaker_access_is_limited_to_own_tasks(): void
    {
        $admin = $this->incidentAdmin();
        $assigned = $this->caretaker();
        $outsider = $this->caretaker();
        $ticket = $this->createIncident($this->reporter());
        $other = $this->createIncident($this->reporter());

        $this->actingAsUser($admin);
        $taskId = $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $assigned->id])->json('data.assignment.id');

        $this->actingAsUser($outsider);
        $this->postJson("/api/v1/caretaker/tasks/{$taskId}/accept")->assertNotFound();
        $this->getJson("/api/v1/incidents/{$ticket['id']}")->assertNotFound();
        $this->postJson("/api/v1/incidents/{$ticket['id']}/status", ['status' => 'IN_PROGRESS', 'note' => 'x'], $this->key())->assertNotFound();

        $this->actingAsUser($assigned);
        $this->getJson("/api/v1/incidents/{$ticket['id']}")->assertOk()->assertJsonStructure(['data' => ['media', 'location', 'status_logs']]);
        $this->getJson("/api/v1/incidents/{$other['id']}")->assertNotFound();
        // Belum menerima tugas: belum boleh mengubah status.
        $this->postJson("/api/v1/incidents/{$ticket['id']}/status", ['status' => 'IN_PROGRESS', 'note' => 'x'], $this->key())->assertForbidden();
    }

    public function test_adopted_cat_ticket_is_handled_by_admin_not_dispatched(): void
    {
        $admin = $this->incidentAdmin();
        $cat = $this->cat(['presence_status' => 'ADOPTED', 'adoption_status' => 'ADOPTED']);
        $ticket = $this->createIncident($this->reporter(), ['cat_id' => $cat->id, 'source' => 'ADOPTION', 'source_ref' => 'adoption-issue-1']);

        $this->actingAsUser($admin);
        $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/assign", ['caretaker_id' => $this->caretaker()->id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');

        $this->postJson("/api/v1/incidents/{$ticket['id']}/status", ['status' => 'IN_PROGRESS', 'note' => 'Admin menghubungi adopter.'], $this->key())
            ->assertOk()
            ->assertJsonPath('data.status', 'IN_PROGRESS')
            ->assertJsonPath('data.handled_by_admin_only', true);
    }

    public function test_reject_and_duplicate_require_close_permission_and_give_no_points(): void
    {
        $this->seedIncidentPointRule();
        $reporter = $this->reporter();
        $original = $this->createIncident($reporter);
        $copy = $this->createIncident($reporter);
        $spam = $this->createIncident($reporter, ['category' => 'OTHER']);

        $this->actingAsUser($this->incidentAdmin(['incident.view', 'incident.dispatch']));
        $this->postJson("/api/v1/incidents/{$spam['id']}/status", ['status' => 'REJECTED', 'note' => 'Bukan insiden kucing.'], $this->key())
            ->assertForbidden();
        $this->postJson("/api/v1/admin/incidents/{$spam['id']}/close", ['note' => 'x'])->assertForbidden();

        $this->actingAsUser($this->incidentAdmin());
        $this->postJson("/api/v1/incidents/{$spam['id']}/status", ['status' => 'REJECTED', 'note' => 'Bukan insiden kucing.'], $this->key())
            ->assertOk()
            ->assertJsonPath('data.status', 'REJECTED');
        $this->postJson("/api/v1/incidents/{$copy['id']}/status", ['status' => 'DUPLICATE', 'note' => 'Sama dengan tiket sebelumnya.', 'duplicate_of' => $original['id']], $this->key())
            ->assertOk()
            ->assertJsonPath('data.duplicate_of', $original['id']);

        // Tiket final tidak dapat dibuka kembali.
        $this->postJson("/api/v1/incidents/{$spam['id']}/status", ['status' => 'IN_PROGRESS', 'note' => 'Buka lagi'], $this->key())
            ->assertStatus(409);
        // ASSIGNED tidak dapat ditetapkan manual.
        $this->postJson("/api/v1/incidents/{$original['id']}/status", ['status' => 'ASSIGNED', 'note' => 'x'], $this->key())
            ->assertStatus(409);

        $this->assertSame(0, DB::table('point_transactions')->count());
    }

    public function test_status_validation_and_unknown_ticket(): void
    {
        $this->actingAsUser($this->incidentAdmin());
        $ticket = $this->createIncident($this->reporter());
        $this->actingAsUser($this->incidentAdmin());

        $this->postJson("/api/v1/incidents/{$ticket['id']}/status", ['status' => 'DONE'], $this->key())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['status', 'note']]);

        $this->postJson('/api/v1/incidents/' . Str::ulid() . '/status', ['status' => 'IN_PROGRESS', 'note' => 'x'], $this->key())
            ->assertNotFound();

        $this->patchJson("/api/v1/admin/incidents/{$ticket['id']}", ['urgency' => 'LOW', 'reason' => 'Kucing hanya lemas ringan.'])
            ->assertOk()
            ->assertJsonPath('data.urgency', 'LOW');
        $this->assertDatabaseHas('audit_logs', ['action' => 'incident.urgency_changed', 'subject_id' => $ticket['id']]);
    }
}
