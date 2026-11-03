<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffSpaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_the_month_summary_and_past_days_newest_first(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 20:00'));
        $staff = User::factory()->create();
        WorkSchedule::factory()->create(['user_id' => $staff->id, 'weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        Punch::factory()->create(['user_id' => $staff->id, 'type' => PunchType::Arrival, 'punched_at' => '2026-10-12 09:12']);
        Punch::factory()->create(['user_id' => $staff->id, 'type' => PunchType::Departure, 'punched_at' => '2026-10-12 18:00']);

        $this->actingAs($staff)
            ->get(route('staff.history', ['mois' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/History')
                ->has('days', 14)
                ->where('days.0.date', '2026-10-14')
                ->where('days.2.firstArrival', '09:12')
                ->where('days.2.lastDeparture', '18:00')
                ->where('days.2.lateMinutes', 12)
                ->where('summary.workedDays', 1)
                ->where('summary.lateCount', 1));
    }

    public function test_schedule_shows_two_weeks_and_accepted_leave(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00'));
        $staff = User::factory()->create();
        WorkSchedule::factory()->create(['user_id' => $staff->id, 'weekday' => 4, 'starts_at' => '10:00', 'ends_at' => '19:00']);
        LeaveRequest::factory()->approved()->create(['user_id' => $staff->id, 'starts_on' => '2026-10-20', 'ends_on' => '2026-10-21']);

        $this->actingAs($staff)
            ->get(route('staff.schedule'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Schedule')
                ->has('weeks', 2)
                ->has('weeks.0', 7)
                ->where('weeks.0.3.scheduleLabel', '10:00 – 19:00')
                ->where('weeks.1.1.leave.type', 'Congé')
                ->has('upcomingLeaves', 1));
    }

    public function test_scanning_the_tablet_link_prefills_the_code(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('punch.create', ['code' => '1.ABC.123.signature']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Staff/Punch')
                ->where('scannedToken', '1.ABC.123.signature')
                ->where('actions.0.value', 'arrival'));
    }

    public function test_guest_scanning_the_tablet_link_is_sent_to_login(): void
    {
        $this->get(route('punch.create', ['code' => 'abc']))
            ->assertRedirect(route('login'));
    }
}
