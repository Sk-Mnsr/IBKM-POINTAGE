<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PunchType;
use App\Models\Punch;
use App\Models\PunchCorrection;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\AttendanceCalculator;
use App\Support\DailyAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PunchCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-14 20:00'));
        $this->admin = User::factory()->superAdmin()->create();
        $this->employee = User::factory()->create(['name' => 'Awa Ndiaye']);

        WorkSchedule::factory()->create([
            'user_id' => $this->employee->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);
    }

    public function test_adding_a_forgotten_departure_completes_the_day_and_is_logged(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:00');
        $this->assertSame(AttendanceStatus::Incomplete, $this->day('2026-10-12')->status);

        $this->actingAs($this->admin)
            ->from(route('reports.employee', $this->employee))
            ->post(route('punches.corrections.store', $this->employee), [
                'date' => '2026-10-12',
                'time' => '18:00',
                'type' => 'departure',
                'reason' => 'Oubli de pointage, confirmé par l\'employée',
            ])
            ->assertRedirect(route('reports.employee', $this->employee))
            ->assertSessionHas('status', 'Départ ajouté(e) à 18:00.');

        $day = $this->day('2026-10-12');
        $this->assertSame(AttendanceStatus::Worked, $day->status);
        $this->assertSame(540, $day->workedMinutes);

        $added = Punch::query()->where('type', PunchType::Departure)->sole();
        $this->assertTrue($added->is_manual);
        $this->assertNull($added->kiosk_id);

        $correction = PunchCorrection::query()->sole();
        $this->assertSame($added->id, $correction->punch_id);
        $this->assertSame($this->employee->id, $correction->user_id);
        $this->assertSame($this->admin->id, $correction->author_id);
        $this->assertSame(PunchCorrection::ACTION_ADDED, $correction->action);
        $this->assertSame('2026-10-12', $correction->punch_date->toDateString());
        $this->assertSame('Oubli de pointage, confirmé par l\'employée', $correction->reason);
    }

    public function test_correction_that_breaks_the_order_of_the_day_is_refused(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:00');
        $this->punch(PunchType::Departure, '2026-10-12 18:00');

        $this->actingAs($this->admin)
            ->post(route('punches.corrections.store', $this->employee), [
                'date' => '2026-10-12',
                'time' => '08:00',
                'type' => 'departure',
                'reason' => 'Test',
            ])
            ->assertSessionHasErrors([
                'punch' => 'Journée incohérente après correction : « Départ » ne peut pas suivre « le début de la journée ». Vérifiez l\'ordre et les heures.',
            ]);

        $this->assertSame(2, Punch::query()->count());
        $this->assertDatabaseCount('punch_corrections', 0);
    }

    public function test_moving_an_arrival_recalculates_lateness_and_keeps_before_and_after(): void
    {
        $arrival = $this->punch(PunchType::Arrival, '2026-10-12 09:40');
        $this->punch(PunchType::Departure, '2026-10-12 18:00');

        $this->actingAs($this->admin)
            ->put(route('punches.corrections.update', $arrival), [
                'time' => '09:02',
                'type' => 'arrival',
                'reason' => 'Borne en panne ce matin',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->day('2026-10-12')->lateMinutes);

        $correction = PunchCorrection::query()->sole();
        $this->assertSame(PunchCorrection::ACTION_UPDATED, $correction->action);
        $this->assertSame('09:40', $correction->before['time']);
        $this->assertSame('09:02', $correction->after['time']);
    }

    public function test_deleting_a_duplicate_punch_archives_it_and_removes_it_from_the_hours(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:00');
        $this->punch(PunchType::BreakStart, '2026-10-12 13:00');
        $duplicate = $this->punch(PunchType::BreakStart, '2026-10-12 13:01');
        $this->punch(PunchType::BreakEnd, '2026-10-12 14:00');
        $this->punch(PunchType::Departure, '2026-10-12 18:00');

        $this->actingAs($this->admin)
            ->delete(route('punches.corrections.destroy', $duplicate), ['reason' => 'Double scan'])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($duplicate);
        $this->assertSame(480, $this->day('2026-10-12')->workedMinutes);
        $this->assertDatabaseHas('punch_corrections', [
            'punch_id' => $duplicate->id,
            'action' => PunchCorrection::ACTION_DELETED,
            'reason' => 'Double scan',
        ]);
    }

    public function test_a_reason_is_required(): void
    {
        $arrival = $this->punch(PunchType::Arrival, '2026-10-12 09:00');

        $this->actingAs($this->admin)
            ->delete(route('punches.corrections.destroy', $arrival), ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Indiquez le motif de la suppression.']);

        $this->assertNotSoftDeleted($arrival);
    }

    public function test_a_punch_cannot_be_added_in_the_future(): void
    {
        $this->actingAs($this->admin)
            ->post(route('punches.corrections.store', $this->employee), [
                'date' => '2026-10-15',
                'time' => '09:00',
                'type' => 'arrival',
                'reason' => 'Anticipation',
            ])
            ->assertSessionHasErrors(['time' => 'Un pointage ne peut pas être placé dans le futur.']);
    }

    public function test_staff_cannot_correct_punches(): void
    {
        $arrival = $this->punch(PunchType::Arrival, '2026-10-12 09:00');

        $this->actingAs($this->employee)
            ->delete(route('punches.corrections.destroy', $arrival), ['reason' => 'Je préfère'])
            ->assertForbidden();

        $this->assertNotSoftDeleted($arrival);
    }

    public function test_employee_sheet_lists_the_month_corrections_including_the_last_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 10:00'));
        $this->punch(PunchType::Arrival, '2026-10-12 09:00');
        $this->punch(PunchType::Arrival, '2026-10-31 09:00');

        foreach (['2026-10-12', '2026-10-31'] as $date) {
            $this->actingAs($this->admin)->post(route('punches.corrections.store', $this->employee), [
                'date' => $date,
                'time' => '18:00',
                'type' => 'departure',
                'reason' => 'Oubli',
            ]);
        }

        $this->actingAs($this->admin)
            ->get(route('reports.employee', ['employee' => $this->employee, 'mois' => '2026-10']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Employee')
                ->has('corrections', 2)
                ->where('corrections.0.summary', 'Départ à 18:00')
                ->where('corrections.0.reason', 'Oubli')
                ->where('days.11.punches.1.isManual', true)
                ->has('punchTypes', 4));
    }

    private function punch(PunchType $type, string $at): Punch
    {
        return Punch::factory()->create([
            'user_id' => $this->employee->id,
            'type' => $type,
            'punched_at' => $at,
        ]);
    }

    private function day(string $date): DailyAttendance
    {
        $day = CarbonImmutable::parse($date);

        return app(AttendanceCalculator::class)->forUser($this->employee->fresh(), $day, $day)->first();
    }
}
