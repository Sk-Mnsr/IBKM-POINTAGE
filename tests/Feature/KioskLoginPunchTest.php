<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class KioskLoginPunchTest extends TestCase
{
    use RefreshDatabase;

    private Kiosk $kiosk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kiosk = Kiosk::factory()->create();
    }

    public function test_employee_without_phone_punches_with_phone_number_and_password(): void
    {
        $employee = User::factory()->create(['name' => 'Awa Ndiaye', 'phone' => '771234567']);

        $identified = $this->fromPairedTablet()
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '77 123 45 67', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('givenName', 'Awa')
            ->assertJsonPath('actions.0.value', PunchType::Arrival->value);

        $this->fromPairedTablet()
            ->postJson(route('kiosk.punch', $this->kiosk), ['ticket' => $identified->json('ticket'), 'type' => PunchType::Arrival->value])
            ->assertCreated()
            ->assertJsonPath('message', 'Bonjour Awa');

        $this->assertDatabaseHas('punches', [
            'user_id' => $employee->id,
            'kiosk_id' => $this->kiosk->id,
            'type' => PunchType::Arrival->value,
            'via_kiosk_login' => true,
        ]);
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['phone' => '771234567']);

        $this->fromPairedTablet()
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'mauvais'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Téléphone ou mot de passe incorrect.']);
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        User::factory()->create(['phone' => '771234567']);

        foreach (range(1, 5) as $attempt) {
            $this->fromPairedTablet()->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'mauvais']);
        }

        $this->fromPairedTablet()
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_employee_of_another_shop_cannot_punch_on_this_kiosk(): void
    {
        User::factory()->create(['phone' => '771234567', 'shop_id' => Shop::factory()->create()->id]);

        $this->fromPairedTablet()
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'Ce compte n’appartient pas à cette boutique.']);
    }

    public function test_managers_cannot_punch_with_their_credentials(): void
    {
        User::factory()->superAdmin()->create(['phone' => '771234567']);

        $this->fromPairedTablet()
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_unpaired_tablet_cannot_use_credentials(): void
    {
        User::factory()->create(['phone' => '771234567']);

        $this->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_ticket_works_only_once(): void
    {
        User::factory()->create(['phone' => '771234567']);
        $ticket = $this->identify('771234567')->json('ticket');

        $this->fromPairedTablet()
            ->postJson(route('kiosk.punch', $this->kiosk), ['ticket' => $ticket, 'type' => PunchType::Arrival->value])
            ->assertCreated();

        $this->fromPairedTablet()
            ->postJson(route('kiosk.punch', $this->kiosk), ['ticket' => $ticket, 'type' => PunchType::BreakStart->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ticket');

        $this->assertSame(1, Punch::query()->count());
    }

    public function test_ticket_expires_after_two_minutes(): void
    {
        User::factory()->create(['phone' => '771234567']);
        $ticket = $this->identify('771234567')->json('ticket');

        $this->travel(3)->minutes();

        $this->fromPairedTablet()
            ->postJson(route('kiosk.punch', $this->kiosk), ['ticket' => $ticket, 'type' => PunchType::Arrival->value])
            ->assertJsonValidationErrors('ticket');
    }

    public function test_ticket_cannot_be_used_on_another_kiosk(): void
    {
        User::factory()->create(['phone' => '771234567']);
        $ticket = $this->identify('771234567')->json('ticket');
        $otherKiosk = Kiosk::factory()->create(['shop_id' => $this->kiosk->shop_id]);

        $this->withCredentials()
            ->withCookie($otherKiosk->pairingCookieName(), $otherKiosk->pairingCookieValue())
            ->postJson(route('kiosk.punch', $otherKiosk), ['ticket' => $ticket, 'type' => PunchType::Arrival->value])
            ->assertJsonValidationErrors('ticket');
    }

    public function test_usual_punch_rules_still_apply(): void
    {
        User::factory()->create(['phone' => '771234567']);
        $ticket = $this->identify('771234567')->json('ticket');

        $this->fromPairedTablet()
            ->postJson(route('kiosk.punch', $this->kiosk), ['ticket' => $ticket, 'type' => PunchType::Departure->value])
            ->assertJsonValidationErrors('type');

        $this->assertSame(0, Punch::query()->count());
    }

    private function identify(string $phone): TestResponse
    {
        return $this->fromPairedTablet()
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => $phone, 'password' => 'password'])
            ->assertOk();
    }

    private function fromPairedTablet(): static
    {
        return $this->withCredentials()->withCookie($this->kiosk->pairingCookieName(), $this->kiosk->pairingCookieValue());
    }
}
