<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PunchHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_filters_by_employee(): void
    {
        $admin = User::factory()->admin()->create();
        $awa = User::factory()->create(['name' => 'Awa Ndiaye']);
        $fatou = User::factory()->create(['name' => 'Fatou Diop']);
        Punch::factory()->create(['user_id' => $awa->id, 'punched_at' => now()->setTime(9, 1, 0)]);
        Punch::factory()->create(['user_id' => $fatou->id, 'punched_at' => now()->setTime(9, 2, 0)]);

        $this->actingAs($admin)
            ->get(route('punches.index', ['employe' => $awa->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Punches/Index')
                ->where('punches.total', 1)
                ->where('punches.data.0.employee.name', 'Awa Ndiaye')
                ->where('punches.data.0.timeWithSeconds', '09:01:00')
                ->where('filters.employe', $awa->id));
    }

    public function test_history_summarises_the_period_and_flags_late_arrivals(): void
    {
        $admin = User::factory()->admin()->create();
        $awa = User::factory()->create();
        WorkSchedule::factory()->create([
            'user_id' => $awa->id,
            'weekday' => now()->dayOfWeekIso,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);
        Punch::factory()->create(['user_id' => $awa->id, 'type' => PunchType::Arrival, 'punched_at' => now()->setTime(9, 20)]);
        Punch::factory()->create(['user_id' => $awa->id, 'type' => PunchType::Departure, 'punched_at' => now()->setTime(18, 0)]);
        Punch::factory()->create(['type' => PunchType::Arrival, 'punched_at' => now()->setTime(8, 0)]);

        $this->actingAs($admin)
            ->get(route('punches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 3)
                ->where('stats.arrivals', 2)
                ->where('stats.departures', 1)
                ->where('stats.employees', 2)
                ->where('punches.data.1.type.value', 'arrival')
                ->where('punches.data.1.lateMinutes', 20)
                ->missing('punches.data.0.lateMinutes'));
    }

    public function test_history_excludes_punches_outside_the_dates(): void
    {
        $admin = User::factory()->admin()->create();
        Punch::factory()->create(['punched_at' => now()->subDays(30)->setTime(8, 45, 0)]);

        $this->actingAs($admin)
            ->get(route('punches.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Punches/Index')
                ->where('punches.total', 0)
                ->has('punches.data', 0));
    }

    public function test_history_exports_to_excel(): void
    {
        Punch::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('punches.export', ['du' => '2026-10-01', 'au' => '2026-10-31']))
            ->assertOk()
            ->assertDownload('pointages-20261001-20261031.xlsx');
    }
}
