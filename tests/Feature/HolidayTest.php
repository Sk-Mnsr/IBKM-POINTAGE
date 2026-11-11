<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PunchType;
use App\Models\Holiday;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2027-05-20 20:00'));
        $this->employee = User::factory()->create();

        foreach (range(1, 5) as $weekday) {
            WorkSchedule::factory()->create([
                'user_id' => $this->employee->id,
                'weekday' => $weekday,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
            ]);
        }
    }

    public function test_missed_holiday_is_not_an_absence(): void
    {
        Holiday::factory()->create(['date' => '2027-05-17', 'name' => 'Tabaski']);

        $day = app(AttendanceCalculator::class)
            ->forUser($this->employee, CarbonImmutable::parse('2027-05-17'), CarbonImmutable::parse('2027-05-17'))
            ->first();

        $this->assertSame(AttendanceStatus::Holiday, $day->status);
        $this->assertFalse($day->isScheduled());
        $this->assertSame(0, $day->scheduledMinutes);
        $this->assertSame('Férié · Tabaski', $day->scheduleLabel());

        $summary = AttendanceSummary::fromDays(collect([$day]));
        $this->assertSame(0, $summary->absentDays);
        $this->assertSame(1, $summary->holidayDays);
    }

    public function test_work_on_a_holiday_counts_as_overtime_without_lateness(): void
    {
        Holiday::factory()->create(['date' => '2027-05-17']);
        Punch::factory()->create(['user_id' => $this->employee->id, 'type' => PunchType::Arrival, 'punched_at' => '2027-05-17 10:00']);
        Punch::factory()->create(['user_id' => $this->employee->id, 'type' => PunchType::Departure, 'punched_at' => '2027-05-17 14:00']);

        $day = app(AttendanceCalculator::class)
            ->forUser($this->employee, CarbonImmutable::parse('2027-05-17'), CarbonImmutable::parse('2027-05-17'))
            ->first();

        $this->assertSame(AttendanceStatus::Worked, $day->status);
        $this->assertSame(0, $day->lateMinutes);
        $this->assertSame(240, $day->overtimeMinutes);
    }

    public function test_known_holidays_are_computed_for_a_year(): void
    {
        $suggestions = Holiday::suggestionsFor(2027);

        $this->assertSame('Fête de l’Indépendance', $suggestions['2027-04-04']);
        $this->assertSame('Lundi de Pâques', $suggestions['2027-03-29']);
        $this->assertSame('Ascension', $suggestions['2027-05-06']);
        $this->assertSame('Lundi de Pentecôte', $suggestions['2027-05-17']);
        $this->assertCount(9, $suggestions);
    }

    public function test_super_admin_adds_the_known_holidays_of_a_year_once(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('holidays.suggestions'), ['year' => 2027])->assertRedirect();
        $this->actingAs($superAdmin)->post(route('holidays.suggestions'), ['year' => 2027])->assertRedirect();

        $this->assertSame(9, Holiday::query()->count());
    }

    public function test_super_admin_adds_and_removes_a_lunar_holiday(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->post(route('holidays.store'), ['date' => '2027-05-18', 'name' => 'Tabaski'])
            ->assertSessionHasNoErrors();

        $this->actingAs($superAdmin)
            ->post(route('holidays.store'), ['date' => '2027-05-18', 'name' => 'Doublon'])
            ->assertSessionHasErrors(['date' => 'Un jour férié est déjà enregistré à cette date.']);

        $this->actingAs($superAdmin)
            ->get(route('settings.edit', ['annee' => 2027]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Edit')
                ->where('holidays.0.name', 'Tabaski')
                ->has('missingSuggestions', 9));

        $this->actingAs($superAdmin)
            ->delete(route('holidays.destroy', Holiday::query()->sole()))
            ->assertRedirect();

        $this->assertSame(0, Holiday::query()->count());
    }

    public function test_shop_manager_cannot_change_holidays(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('holidays.store'), ['date' => '2027-05-17', 'name' => 'Tabaski'])
            ->assertForbidden();
    }
}
