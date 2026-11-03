<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\User;
use App\Support\KioskToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PunchRecordingTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_arrival_is_recorded_from_the_tablet_code(): void
    {
        $staff = User::factory()->create(['name' => 'Awa Ndiaye']);
        $kiosk = Kiosk::factory()->create();
        $token = app(KioskToken::class)->issue($kiosk)['token'];

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => $token,
            'type' => PunchType::Arrival->value,
        ])->assertRedirect(route('punch.create'))
            ->assertSessionHas('status', 'Bonjour Awa');

        $this->assertDatabaseHas('punches', [
            'user_id' => $staff->id,
            'kiosk_id' => $kiosk->id,
            'type' => PunchType::Arrival->value,
        ]);
    }

    public function test_staff_signs_in_with_a_phone_number(): void
    {
        User::factory()->create([
            'phone' => '771000001',
        ]);

        $this->post(route('login.store'), [
            'identifier' => '771000001',
            'password' => 'password',
        ])->assertRedirect(route('punch.create'));
    }

    public function test_departure_before_arrival_is_rejected(): void
    {
        $staff = User::factory()->create();
        $token = app(KioskToken::class)->issue(Kiosk::factory()->create())['token'];

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => $token,
            'type' => PunchType::Departure->value,
        ])->assertSessionHasErrors([
            'type' => 'Cette action n\'est pas disponible pour le moment.',
        ]);

        $this->assertDatabaseCount('punches', 0);
    }

    public function test_expired_tablet_code_is_rejected(): void
    {
        $staff = User::factory()->create();
        $kiosk = Kiosk::factory()->create();

        $this->freezeTime();
        $token = app(KioskToken::class)->issue($kiosk)['token'];
        $this->travel(2)->minutes();

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => $token,
            'type' => PunchType::Arrival->value,
        ])->assertSessionHasErrors([
            'token' => 'Ce code a expiré. Scannez le code actuellement affiché sur la tablette.',
        ]);
    }

    public function test_tampered_tablet_code_is_rejected(): void
    {
        $staff = User::factory()->create();
        $token = app(KioskToken::class)->issue(Kiosk::factory()->create())['token'];
        $tampered = substr($token, 0, -1).(str_ends_with($token, 'a') ? 'b' : 'a');

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => $tampered,
            'type' => PunchType::Arrival->value,
        ])->assertSessionHasErrors('token');

        $this->assertDatabaseCount('punches', 0);
    }

    public function test_admin_cannot_punch(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('punch.create'))
            ->assertForbidden();
    }

    public function test_code_one_window_ahead_is_accepted_for_tablet_clock_drift(): void
    {
        $this->freezeTime();
        $staff = User::factory()->create();
        $kiosk = Kiosk::factory()->create();
        $token = app(KioskToken::class)->issue($kiosk, now()->addSeconds(30)->getTimestamp())['token'];

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => $token,
            'type' => PunchType::Arrival->value,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('punches', 1);
    }

    public function test_offline_punch_is_recorded_at_the_scan_time_and_flagged(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 08:58:10'));
        $staff = User::factory()->create(['name' => 'Awa Ndiaye']);
        $kiosk = Kiosk::factory()->create();
        $token = app(KioskToken::class)->issue($kiosk)['token'];
        $uuid = (string) Str::uuid();

        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:15:00'));

        $this->actingAs($staff)->postJson(route('punch.sync'), [
            'uuid' => $uuid,
            'token' => $token,
            'type' => 'arrival',
            'scanned_at' => '2026-10-19T08:58:12+00:00',
        ])->assertCreated()
            ->assertJson(['status' => 'recorded', 'message' => 'Bonjour Awa', 'time' => '08:58']);

        $punch = Punch::query()->sole();
        $this->assertSame($uuid, $punch->uuid);
        $this->assertTrue($punch->is_offline);
        $this->assertSame('2026-10-19 08:58:12', $punch->punched_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($punch->synced_at);
    }

    public function test_offline_scan_time_cannot_be_moved_outside_the_scanned_code(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 09:20:05'));
        $staff = User::factory()->create();
        $token = app(KioskToken::class)->issue(Kiosk::factory()->create())['token'];

        $this->travelTo(CarbonImmutable::parse('2026-10-19 11:00:00'));

        $this->actingAs($staff)->postJson(route('punch.sync'), [
            'uuid' => (string) Str::uuid(),
            'token' => $token,
            'type' => 'arrival',
            'scanned_at' => '2026-10-19T08:30:00+00:00',
        ])->assertCreated();

        $this->assertSame('2026-10-19 09:20:00', Punch::query()->sole()->punched_at->format('Y-m-d H:i:s'));
    }

    public function test_sending_the_same_offline_punch_twice_records_it_once(): void
    {
        $staff = User::factory()->create();
        $payload = [
            'uuid' => (string) Str::uuid(),
            'token' => app(KioskToken::class)->issue(Kiosk::factory()->create())['token'],
            'type' => 'arrival',
            'scanned_at' => now()->toIso8601String(),
        ];

        $this->actingAs($staff)->postJson(route('punch.sync'), $payload)->assertCreated();
        $this->actingAs($staff)->postJson(route('punch.sync'), $payload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('punches', 1);
    }

    public function test_offline_code_older_than_a_day_is_rejected(): void
    {
        $this->freezeTime();
        $staff = User::factory()->create();
        $token = app(KioskToken::class)->issue(Kiosk::factory()->create())['token'];
        $this->travel(25)->hours();

        $this->actingAs($staff)->postJson(route('punch.sync'), [
            'uuid' => (string) Str::uuid(),
            'token' => $token,
            'type' => 'arrival',
            'scanned_at' => now()->subHours(25)->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'Ce code a expiré. Scannez le code actuellement affiché sur la tablette.']);

        $this->assertDatabaseCount('punches', 0);
    }

    public function test_offline_punches_respect_the_order_of_the_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00:00'));
        $staff = User::factory()->create();
        $token = app(KioskToken::class)->issue(Kiosk::factory()->create())['token'];

        $this->actingAs($staff)->postJson(route('punch.sync'), [
            'uuid' => (string) Str::uuid(),
            'token' => $token,
            'type' => 'departure',
            'scanned_at' => now()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Cette action n\'est pas disponible pour le moment.']);
    }

    public function test_offline_punch_identifier_of_a_colleague_is_refused(): void
    {
        $punch = Punch::factory()->create();

        $this->actingAs(User::factory()->create())->postJson(route('punch.sync'), [
            'uuid' => $punch->uuid,
            'token' => app(KioskToken::class)->issue(Kiosk::factory()->create())['token'],
            'type' => 'arrival',
            'scanned_at' => now()->toIso8601String(),
        ])->assertStatus(409);
    }

    public function test_inactive_staff_is_signed_out(): void
    {
        $staff = User::factory()->inactive()->create();
        $token = app(KioskToken::class)->issue(Kiosk::factory()->create())['token'];

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => $token,
            'type' => PunchType::Arrival->value,
        ])->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
