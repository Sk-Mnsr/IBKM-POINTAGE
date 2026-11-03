<?php

namespace Tests\Feature;

use App\Enums\PunchType;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-14 20:00'));

        $this->admin = User::factory()->admin()->create();
        $this->employee = User::factory()->create(['name' => 'Awa Ndiaye']);

        WorkSchedule::factory()->create([
            'user_id' => $this->employee->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);

        Punch::factory()->create(['user_id' => $this->employee->id, 'type' => PunchType::Arrival, 'punched_at' => '2026-10-12 09:30']);
        Punch::factory()->create(['user_id' => $this->employee->id, 'type' => PunchType::Departure, 'punched_at' => '2026-10-12 19:30']);
    }

    public function test_monthly_report_lists_hours_and_lateness_per_employee(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.index', ['mois' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Index')
                ->where('month.label', 'Octobre 2026')
                ->where('rows.0.employee.name', 'Awa Ndiaye')
                ->where('rows.0.summary.workedMinutes', 600)
                ->where('rows.0.summary.lateMinutes', 30)
                ->where('rows.0.summary.overtimeMinutes', 60)
                ->where('rows.0.summary.absentDays', 1)
                ->where('totals.workedMinutes', 600));
    }

    public function test_monthly_report_downloads_as_excel(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.excel', ['mois' => '2026-10']));

        $response->assertOk();
        $response->assertDownload('rapport-mensuel-2026-10.xlsx');
    }

    public function test_monthly_report_downloads_as_pdf(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('reports.pdf', ['mois' => '2026-10']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_employee_sheet_shows_each_day_and_downloads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.employee', ['employee' => $this->employee, 'mois' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reports/Employee')
                ->has('days', 31)
                ->where('days.11.dateLabel', 'Lun. 12/10')
                ->where('days.11.firstArrival', '09:30')
                ->where('days.11.lastDeparture', '19:30')
                ->where('days.11.lateMinutes', 30)
                ->where('days.4.status.value', 'absent')
                ->where('days.18.status.value', 'upcoming'));

        $this->actingAs($this->admin)
            ->get(route('reports.employee.excel', ['employee' => $this->employee, 'mois' => '2026-10']))
            ->assertDownload('fiche-awa-ndiaye-2026-10.xlsx');

        $this->actingAs($this->admin)
            ->get(route('reports.employee.pdf', ['employee' => $this->employee, 'mois' => '2026-10']))
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_staff_cannot_open_reports(): void
    {
        $this->actingAs($this->employee)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    public function test_admin_account_has_no_employee_sheet(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.employee', ['employee' => $this->admin]))
            ->assertNotFound();
    }

    public function test_dashboard_shows_today_status_and_counts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00'));
        Punch::factory()->create(['user_id' => $this->employee->id, 'type' => PunchType::Arrival, 'punched_at' => '2026-10-19 09:15']);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('rows.0.employee.name', 'Awa Ndiaye')
                ->where('rows.0.day.status.label', 'Au travail')
                ->where('rows.0.day.lateMinutes', 15)
                ->where('counts.onSite', 1)
                ->where('counts.late', 1)
                ->where('counts.arrived', 1)
                ->where('counts.awaited', 1)
                ->has('departments', 1)
                ->where('departments.0.arrived', 1)
                ->has('chart', 7)
                ->where('chart.6.late', 1));
    }
}
