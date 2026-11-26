<?php

namespace App\Modules\IncidentReport\Tests\Feature;

use App\Modules\IncidentReport\Models\IncidentMedia;
use App\Modules\IncidentReport\Models\IncidentReport;
use App\Modules\IncidentReport\Models\IncidentStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Optimind EIR-01..05, EIR-10, FIX-02, FIX-09: pembuatan dan pembacaan tiket oleh pelapor.
 */
class IncidentReportApiTest extends TestCase
{
    use RefreshDatabase;
    use IncidentTestHelpers;

    public function test_reporter_creates_ticket_with_received_status_after_save(): void
    {
        $reporter = $this->reporter();
        $cat = $this->cat();
        $media = [$this->media($reporter), $this->media($reporter)];
        $this->actingAsUser($reporter);

        $response = $this->postJson('/api/v1/incidents', $this->payload([
            'cat_id' => $cat->id,
            'media_ids' => $media,
            'source' => 'PROFILE',
            'source_ref' => $cat->id,
        ]), $this->key());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'RECEIVED')
            ->assertJsonPath('data.category', 'INJURED')
            ->assertJsonPath('data.urgency', 'HIGH')
            ->assertJsonPath('data.cat.id', $cat->id)
            ->assertJsonPath('data.cat.name', 'Oyen')
            ->assertJsonPath('data.source', 'PROFILE')
            ->assertJsonPath('data.source_ref', $cat->id)
            ->assertJsonPath('data.location.lat', -6.97452)
            ->assertJsonPath('data.location.lng', 107.6318)
            ->assertJsonPath('data.location.accuracy_m', 12)
            ->assertJsonPath('data.media_count', 2)
            ->assertJsonPath('data.has_assignee', false)
            // FIX-09: tidak ada klaim relawan sudah diberi tahu.
            ->assertJsonPath('data.notification_status', 'NOT_REQUESTED')
            ->assertJsonPath('data.status_logs.0.from_status', null)
            ->assertJsonPath('data.status_logs.0.to_status', 'RECEIVED')
            ->assertJsonPath('data.status_logs.0.actor.name', 'Pelapor');

        $this->assertMatchesRegularExpression('/^INC-\d{6}-[A-Z0-9]{6}$/', $response->json('data.ticket_code'));
        $this->assertStringNotContainsStringIgnoringCase('relawan telah', $response->json('message'));
        $this->assertArrayNotHasKey('reporter', $response->json('data'));
        $this->assertArrayNotHasKey('media', $response->json('data'));

        $incident = IncidentReport::sole();
        $this->assertSame($reporter->id, $incident->reporter_id);
        $this->assertSame(2, IncidentMedia::where('incident_id', $incident->id)->count());
        $this->assertSame(1, IncidentStatusLog::where('incident_id', $incident->id)->count());
    }

    public function test_retry_with_same_idempotency_key_does_not_duplicate_ticket(): void
    {
        $this->actingAsUser($this->reporter());
        $headers = $this->key();

        $first = $this->postJson('/api/v1/incidents', $this->payload(), $headers)->assertCreated();
        $second = $this->postJson('/api/v1/incidents', $this->payload(), $headers)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, IncidentReport::count());

        // Kunci sama dengan isi berbeda ditolak.
        $this->postJson('/api/v1/incidents', $this->payload(['urgency' => 'LOW']), $headers)
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    public function test_create_requires_idempotency_key(): void
    {
        $this->actingAsUser($this->reporter());

        $this->postJson('/api/v1/incidents', $this->payload())
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertSame(0, IncidentReport::count());
    }

    public function test_validation_failures_use_standard_error_format_and_save_nothing(): void
    {
        $this->actingAsUser($this->reporter());

        $this->postJson('/api/v1/incidents', ['category' => 'UNKNOWN', 'urgency' => 'CRITICAL', 'description' => 'pendek'], $this->key())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['category', 'urgency', 'description', 'location'], 'request_id']);

        $this->postJson('/api/v1/incidents', $this->payload(['location' => ['lat' => 120, 'lng' => 300]]), $this->key())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['location.lat', 'location.lng']]);

        $this->postJson('/api/v1/incidents', $this->payload(['location' => ['lat' => 0, 'lng' => 0]]), $this->key())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['location']]);

        $this->postJson('/api/v1/incidents', $this->payload(['media_ids' => array_fill(0, 6, str_repeat('A', 26))]), $this->key())
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['media_ids']]);

        $this->assertSame(0, IncidentReport::count());
    }

    public function test_media_of_other_user_or_already_used_is_rejected(): void
    {
        $owner = $this->reporter();
        $other = $this->reporter();
        $foreignMedia = $this->media($other);

        $this->actingAsUser($owner);
        $this->postJson('/api/v1/incidents', $this->payload(['media_ids' => [$foreignMedia]]), $this->key())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $mine = $this->media($owner);
        $this->postJson('/api/v1/incidents', $this->payload(['media_ids' => [$mine]]), $this->key())->assertCreated();
        $this->postJson('/api/v1/incidents', $this->payload(['media_ids' => [$mine]]), $this->key())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        $this->assertSame(1, IncidentReport::count());
    }

    public function test_anonymous_and_unverified_users_are_rejected(): void
    {
        $this->postJson('/api/v1/incidents', $this->payload(), $this->key())->assertUnauthorized();
        $this->getJson('/api/v1/incidents/mine')->assertUnauthorized();

        $this->actingAsUser($this->reporter(['email_verified_at' => null]));
        $this->postJson('/api/v1/incidents', $this->payload(), $this->key())
            ->assertForbidden()
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');

        $this->assertSame(0, IncidentReport::count());
    }

    public function test_reporter_lists_and_reads_only_own_tickets(): void
    {
        $alice = $this->reporter();
        $bob = $this->reporter();
        $aliceTicket = $this->createIncident($alice, ['media_ids' => [$this->media($alice)]]);
        $bobTicket = $this->createIncident($bob);

        $this->actingAsUser($alice);
        $list = $this->getJson('/api/v1/incidents/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $aliceTicket['id'])
            ->assertJsonPath('data.0.media_count', 1)
            ->assertJsonPath('meta.total', 1);
        $this->assertStringNotContainsString('081234567890', $list->getContent());
        $this->assertArrayNotHasKey('media', $list->json('data.0'));

        $this->getJson("/api/v1/incidents/{$aliceTicket['id']}")
            ->assertOk()
            ->assertJsonPath('data.ticket_code', $aliceTicket['ticket_code'])
            ->assertJsonCount(1, 'data.status_logs');

        // FIX-02 / IT-11: tiket orang lain disamarkan 404 tanpa isi.
        $denied = $this->getJson("/api/v1/incidents/{$bobTicket['id']}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->assertStringNotContainsString($bobTicket['ticket_code'], $denied->getContent());
    }

    public function test_reporter_cannot_change_status_or_open_admin_endpoints(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->createIncident($reporter);

        $this->postJson("/api/v1/incidents/{$ticket['id']}/status", ['status' => 'RESOLVED', 'note' => 'Sudah'], $this->key())
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->getJson('/api/v1/admin/incidents')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson("/api/v1/admin/incidents/{$ticket['id']}")->assertForbidden();
        $this->postJson("/api/v1/admin/incidents/{$ticket['id']}/verify")->assertForbidden();
        $this->getJson('/api/v1/caretaker/tasks')->assertForbidden();

        $this->assertSame('RECEIVED', IncidentReport::find($ticket['id'])->status);
    }

    public function test_removed_categories_are_rejected_for_new_reports(): void
    {
        $this->actingAsUser($this->reporter());

        foreach (['MISSING', 'FOUND', 'NEW_CAT', 'DAMAGED_QR'] as $category) {
            $this->postJson('/api/v1/incidents', $this->payload(['category' => $category]), $this->key())
                ->assertStatus(422)
                ->assertJsonPath('code', 'VALIDATION_ERROR')
                ->assertJsonStructure(['errors' => ['category']]);
        }

        foreach (['SICK', 'INJURED', 'AGGRESSIVE', 'DECEASED', 'OTHER'] as $category) {
            $this->postJson('/api/v1/incidents', $this->payload(['category' => $category]), $this->key())->assertCreated();
        }

        $this->assertSame(5, IncidentReport::count());
        $this->assertSame(0, DB::table('unidentified_sightings')->count());
    }

    public function test_location_outside_telkom_campus_is_rejected(): void
    {
        $this->actingAsUser($this->reporter());

        // Alun-alun Bandung, di luar kampus.
        $this->postJson('/api/v1/incidents', $this->payload(['location' => ['lat' => -6.9175, 'lng' => 107.6191]]), $this->key())
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['location']]);

        // Tepat di sekitar parkiran Gedung Selaru, di dalam kampus.
        $this->postJson('/api/v1/incidents', $this->payload(['location' => ['lat' => -6.97452, 'lng' => 107.63180]]), $this->key())
            ->assertCreated();

        $this->assertSame(1, IncidentReport::count());
    }

    public function test_repeat_report_from_same_source_is_linked_as_possible_duplicate(): void
    {
        $reporter = $this->reporter();
        $cat = $this->cat();
        $payload = ['cat_id' => $cat->id, 'source' => 'GAME', 'source_ref' => 'encounter-123'];

        $first = $this->createIncident($reporter, $payload);
        $second = $this->createIncident($reporter, $payload + ['description' => 'Kejadian baru: kucing kembali terluka di kaki belakang.']);

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame($first['id'], IncidentReport::find($second['id'])->possible_duplicate_of);
        $this->assertNull(IncidentReport::find($first['id'])->possible_duplicate_of);
    }

    public function test_vets_list_is_empty_without_fictional_data(): void
    {
        $this->actingAsUser($this->reporter());

        $this->getJson('/api/v1/vets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }
}
