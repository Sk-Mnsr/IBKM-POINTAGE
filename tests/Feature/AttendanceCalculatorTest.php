<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\PunchType;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceSummary;
use App\Support\DailyAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-14 20:00'));

        $this->employee = User::factory()->create();

        foreach ([1, 2, 3] as $weekday) {
            WorkSchedule::factory()->create([
                'user_id' => $this->employee->id,
                'weekday' => $weekday,
                'starts_at' => '09:00',
                'ends_at' => '18:00',
            ]);
        }
    }

    public function test_arrival_after_tolerance_counts_the_full_delay_as_late(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:20');

        $day = $this->day('2026-10-12');

        $this->assertSame(20, $day->lateMinutes);
        $this->assertTrue($day->isLate());
    }

    public function test_extreme_lateness_is_capped(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 18:00');

        $day = $this->day('2026-10-12');

        $this->assertSame(180, $day->lateMinutes);
        $this->assertTrue($day->isLate());
    }

    public function test_today_stays_expected_during_absence_grace(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00'));

        $this->assertSame(AttendanceStatus::Expected, $this->day('2026-10-14')->status);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 11:00'));

        $this->assertSame(AttendanceStatus::Absent, $this->day('2026-10-14')->status);
    }

    public function test_arrival_within_tolerance_is_not_late(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:04');

        $this->assertSame(0, $this->day('2026-10-12')->lateMinutes);
    }

    public function test_worked_time_excludes_breaks_and_counts_overtime(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:00');
        $this->punch(PunchType::BreakStart, '2026-10-12 13:00');
        $this->punch(PunchType::BreakEnd, '2026-10-12 13:30');
        $this->punch(PunchType::Departure, '2026-10-12 19:00');

        $day = $this->day('2026-10-12');

        $this->assertSame(570, $day->workedMinutes);
        $this->assertSame(30, $day->breakMinutes);
        $this->assertSame(30, $day->overtimeMinutes);
        $this->assertSame(AttendanceStatus::Worked, $day->status);
    }

    public function test_past_day_without_departure_is_incomplete_and_not_counted(): void
    {
        $this->punch(PunchType::Arrival, '2026-10-12 09:00');

        $day = $this->day('2026-10-12');

        $this->assertSame(AttendanceStatus::Incomplete, $day->status);
        $this->assertSame(0, $day->workedMinutes);
    }

    public function test_scheduled_day_without_punch_is_absent(): void
    {
        $this->assertSame(AttendanceStatus::Absent, $this->day('2026-10-13')->status);
    }

    public function test_approved_leave_replaces_absence(): void
    {
        LeaveRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'starts_on' => '2026-10-13',
            'ends_on' => '2026-10-13',
        ]);

        $this->assertSame(AttendanceStatus::OnLeave, $this->day('2026-10-13')->status);
    }

    public function test_pending_leave_does_not_excuse_absence(): void
    {
        LeaveRequest::factory()->create([
            'user_id' => $this->employee->id,
            'starts_on' => '2026-10-13',
            'ends_on' => '2026-10-13',
        ]);

        $this->assertSame(AttendanceStatus::Absent, $this->day('2026-10-13')->status);
    }

    public function test_leave_covering_a_rest_day_keeps_it_a_day_off(): void
    {
        LeaveRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'starts_on' => '2026-10-10',
            'ends_on' => '2026-10-12',
        ]);

        $this->assertSame(AttendanceStatus::DayOff, $this->day('2026-10-11')->status);
        $this->assertSame(AttendanceStatus::OnLeave, $this->day('2026-10-12')->status);
    }

    public function test_month_summary_ignores_days_after_today(): void
    {
        LeaveRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'starts_on' => '2026-10-19',
            'ends_on' => '2026-10-21',
        ]);

        $days = app(AttendanceCalculator::class)->forUser(
            $this->employee,
            CarbonImmutable::parse('2026-10-19'),
            CarbonImmutable::parse('2026-10-21'),
        );

        $this->assertSame(AttendanceStatus::OnLeave, $days->get('2026-10-19')->status);
        $this->assertSame(0, AttendanceSummary::fromDays($days)->leaveDays);
    }

    public function test_day_without_schedule_is_a_day_off(): void
    {
        $this->assertSame(AttendanceStatus::DayOff, $this->day('2026-10-11')->status);
    }

    public function test_today_before_start_is_expected_and_ongoing_work_is_counted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:30'));
        $this->assertSame(AttendanceStatus::Expected, $this->day('2026-10-14')->status);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 11:00'));
        $this->punch(PunchType::Arrival, '2026-10-14 09:00');

        $day = $this->day('2026-10-14');

        $this->assertSame(AttendanceStatus::Present, $day->status);
        $this->assertSame(120, $day->workedMinutes);
    }

    private function punch(PunchType $type, string $at): void
    {
        Punch::factory()->create([
            'user_id' => $this->employee->id,
            'type' => $type,
            'punched_at' => $at,
        ]);
    }

    private function day(string $date): DailyAttendance
    {
        $day = CarbonImmutable::parse($date);

        return app(AttendanceCalculator::class)->forUser($this->employee, $day, $day)->first();
    }
}
