<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\User;
use App\Support\KioskToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KioskDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_rotating_code_linking_to_the_punch_page(): void
    {
        $this->freezeTime();
        $kiosk = Kiosk::factory()->create(['name' => 'Accueil']);
        $token = app(KioskToken::class)->issue($kiosk)['token'];

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('kiosk.show', $kiosk))
            ->assertOk()
            ->assertDontSee($kiosk->secret)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kiosk/Show')
                ->where('kioskName', 'Accueil')
                ->where('token', $token)
                ->where('scanUrl', route('kiosk.scan', $kiosk))
                ->where('windowSeconds', 30)
                ->where('isPaired', false));
    }

    public function test_unpaired_device_cannot_show_or_fetch_codes(): void
    {
        $kiosk = Kiosk::factory()->create(['name' => 'Accueil']);

        $this->get(route('kiosk.show', $kiosk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kiosk/Unpaired')
                ->where('kioskName', 'Accueil')
                ->missing('token'));

        $this->getJson(route('kiosk.token', $kiosk))->assertForbidden();
        $this->getJson(route('kiosk.tokens', $kiosk))->assertForbidden();
        $this->getJson(route('kiosk.activity', $kiosk))->assertForbidden();
    }

    public function test_staff_account_cannot_show_the_codes(): void
    {
        $kiosk = Kiosk::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('kiosk.tokens', $kiosk))
            ->assertForbidden();
    }

    public function test_admin_pairs_the_tablet_which_then_works_without_a_session(): void
    {
        $kiosk = Kiosk::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('kiosk.pair', $kiosk))
            ->assertRedirect(route('kiosk.show', $kiosk))
            ->assertCookie($kiosk->pairingCookieName(), $kiosk->pairingCookieValue());

        $this->app['auth']->forgetGuards();

        $this->withCredentials()->withCookie($kiosk->pairingCookieName(), $kiosk->pairingCookieValue())
            ->get(route('kiosk.show', $kiosk))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kiosk/Show')
                ->where('isPaired', true));
    }

    public function test_unpairing_revokes_every_paired_tablet(): void
    {
        $kiosk = Kiosk::factory()->create();
        $oldCookie = $kiosk->pairingCookieValue();
        $storedCode = app(KioskToken::class)->issue($kiosk)['token'];

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('kiosk.unpair', $kiosk))
            ->assertRedirect(route('dashboard'));

        $this->app['auth']->forgetGuards();

        $this->withCredentials()->withCookie($kiosk->pairingCookieName(), $oldCookie)
            ->getJson(route('kiosk.tokens', $kiosk))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())->post(route('punch.store'), [
            'token' => $storedCode,
            'type' => PunchType::Arrival->value,
        ])->assertSessionHasErrors(['token' => 'Ce code ne correspond pas à la borne du salon.']);
    }

    public function test_staff_cannot_pair_a_tablet(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('kiosk.pair', Kiosk::factory()->create()))
            ->assertForbidden();
    }

    public function test_paired_tablet_receives_twelve_hours_of_valid_codes(): void
    {
        $this->freezeTime();
        $kiosk = Kiosk::factory()->create();
        $tokens = app(KioskToken::class);

        $response = $this->withCredentials()->withCookie($kiosk->pairingCookieName(), $kiosk->pairingCookieValue())
            ->getJson(route('kiosk.tokens', $kiosk))
            ->assertOk()
            ->assertJsonPath('window_seconds', 30)
            ->assertJsonCount(12 * 120 + 1, 'tokens');

        $current = $response->json('tokens.0');
        $this->assertSame($tokens->window(), $current['window']);
        $this->assertSame($kiosk->id, $tokens->resolve($current['token'])['kiosk']->id);

        $this->travel(6)->hours();
        $later = collect($response->json('tokens'))->firstWhere('window', $tokens->window());
        $this->assertSame($kiosk->id, $tokens->resolve($later['token'])['kiosk']->id);
    }

    public function test_inactive_tablet_is_not_found(): void
    {
        $kiosk = Kiosk::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('kiosk.show', $kiosk))->assertNotFound();
        $this->getJson(route('kiosk.token', $kiosk))->assertNotFound();
        $this->getJson(route('kiosk.tokens', $kiosk))->assertNotFound();
        $this->getJson(route('kiosk.activity', $kiosk))->assertNotFound();
    }

    public function test_tablet_starts_after_the_latest_punch_so_old_ones_are_not_announced(): void
    {
        $kiosk = Kiosk::factory()->create();
        $punch = Punch::factory()->create(['kiosk_id' => $kiosk->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('kiosk.show', $kiosk))
            ->assertInertia(fn (Assert $page) => $page
                ->where('lastPunchId', $punch->id)
                ->where('activityUrl', route('kiosk.activity', $kiosk)));
    }

    public function test_activity_greets_new_punches_on_this_tablet_by_first_name(): void
    {
        $kiosk = Kiosk::factory()->create();
        $awa = User::factory()->create(['name' => 'Awa Ndiaye']);
        $seen = Punch::factory()->create(['kiosk_id' => $kiosk->id, 'user_id' => $awa->id, 'punched_at' => now()->subSeconds(30)]);
        $new = Punch::factory()->create([
            'kiosk_id' => $kiosk->id,
            'user_id' => $awa->id,
            'type' => PunchType::Departure,
            'punched_at' => now()->setTime(18, 5),
        ]);
        Punch::factory()->create(['punched_at' => now()]);

        $this->travelTo(now()->setTime(18, 5, 30));

        $this->withCredentials()->withCookie($kiosk->pairingCookieName(), $kiosk->pairingCookieValue())
            ->getJson(route('kiosk.activity', ['kiosk' => $kiosk, 'apres' => $seen->id]))
            ->assertOk()
            ->assertExactJson([
                'punches' => [[
                    'id' => $new->id,
                    'type' => 'departure',
                    'label' => 'Départ',
                    'message' => 'Au revoir Awa',
                    'time' => '18:05',
                ]],
            ]);
    }

    public function test_activity_ignores_punches_older_than_two_minutes(): void
    {
        $kiosk = Kiosk::factory()->create();
        Punch::factory()->create(['kiosk_id' => $kiosk->id, 'punched_at' => now()->subMinutes(5)]);

        $this->withCredentials()->withCookie($kiosk->pairingCookieName(), $kiosk->pairingCookieValue())
            ->getJson(route('kiosk.activity', $kiosk))
            ->assertOk()
            ->assertExactJson(['punches' => []]);
    }
}
