<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\KioskToken;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlanningPunchRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private Kiosk $kiosk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-15 10:00'));
        $this->employee = User::factory()->create();
        $this->kiosk = Kiosk::factory()->create();

        foreach (range(1, 6) as $weekday) {
            WorkSchedule::factory()->create([
                'user_id' => $this->employee->id,
                'weekday' => $weekday,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
            ]);
        }
    }

    public function test_employee_cannot_punch_on_a_rest_day(): void
    {
        $this->actingAs($this->employee)
            ->post(route('punch.store'), ['token' => $this->token(), 'type' => PunchType::Arrival->value])
            ->assertSessionHasErrors(['type' => 'C’est un jour de repos dans votre planning : le pointage n’est pas possible.']);

        $this->assertDatabaseCount('punches', 0);
    }

    public function test_employee_cannot_punch_while_on_approved_leave(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:00'));
        LeaveRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-18',
        ]);

        $this->actingAs($this->employee)
            ->post(route('punch.store'), ['token' => $this->token(), 'type' => PunchType::Arrival->value])
            ->assertSessionHasErrors(['type' => 'Vous êtes en congé ce jour-là : le pointage n’est pas possible.']);
    }

    public function test_pending_leave_does_not_block_punching(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-16 09:00'));
        LeaveRequest::factory()->create([
            'user_id' => $this->employee->id,
            'starts_on' => '2026-11-16',
            'ends_on' => '2026-11-16',
        ]);

        $this->actingAs($this->employee)
            ->post(route('punch.store'), ['token' => $this->token(), 'type' => PunchType::Arrival->value])
            ->assertSessionHasNoErrors();
    }

    public function test_punch_page_explains_why_there_is_nothing_to_punch(): void
    {
        $this->actingAs($this->employee)
            ->get(route('punch.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('actions', 0)
                ->where('closedReason', 'C’est un jour de repos dans votre planning : le pointage n’est pas possible.'));
    }

    public function test_employee_without_a_planning_yet_can_still_punch(): void
    {
        $newcomer = User::factory()->create();

        $this->actingAs($newcomer)
            ->post(route('punch.store'), ['token' => $this->token(), 'type' => PunchType::Arrival->value])
            ->assertSessionHasNoErrors();
    }

    public function test_exceptional_work_recorded_by_the_manager_can_be_completed_by_the_employee(): void
    {
        Punch::factory()->create([
            'user_id' => $this->employee->id,
            'type' => PunchType::Arrival,
            'punched_at' => '2026-11-15 09:00',
            'is_manual' => true,
            'kiosk_id' => null,
        ]);

        $this->actingAs($this->employee)
            ->post(route('punch.store'), ['token' => $this->token(), 'type' => PunchType::Departure->value])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('punches', 2);
    }

    public function test_kiosk_credentials_flow_shows_the_rest_day_reason(): void
    {
        $this->employee->update(['phone' => '771234567']);

        $this->withCredentials()
            ->withCookie($this->kiosk->pairingCookieName(), $this->kiosk->pairingCookieValue())
            ->postJson(route('kiosk.identify', $this->kiosk), ['phone' => '771234567', 'password' => 'password'])
            ->assertOk()
            ->assertJsonCount(0, 'actions')
            ->assertJsonPath('closedReason', 'C’est un jour de repos dans votre planning : le pointage n’est pas possible.');
    }

    private function token(): string
    {
        return app(KioskToken::class)->issue($this->kiosk)['token'];
    }
}
