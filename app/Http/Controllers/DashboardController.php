<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\PunchResource;
use App\Models\Kiosk;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\User;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceSummary;
use App\Support\DailyAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const array STATUS_ORDER = [
        AttendanceStatus::Absent,
        AttendanceStatus::Present,
        AttendanceStatus::OnBreak,
        AttendanceStatus::Expected,
        AttendanceStatus::Left,
        AttendanceStatus::OnLeave,
        AttendanceStatus::DayOff,
    ];

    public function index(AttendanceCalculator $calculator): Response
    {
        $now = CarbonImmutable::now();
        $today = $now->startOfDay();

        $employees = User::query()
            ->staff()
            ->where('is_active', true)
            ->with('department')
            ->orderBy('name')
            ->get();

        $week = $calculator->forPeriod($employees, $today->subDays(6), $today, $now);
        $todayKey = $today->toDateString();

        $rows = $employees
            ->map(fn (User $employee): array => [
                'employee' => $employee,
                'day' => $week->get($employee->id)->get($todayKey),
            ])
            ->sortBy(fn (array $row): string => sprintf(
                '%02d-%s',
                array_search($row['day']->status, self::STATUS_ORDER, true),
                $row['employee']->name,
            ))
            ->values();

        $todayDays = $rows->pluck('day');

        $monthSummary = AttendanceSummary::combine(
            $calculator->forPeriod($employees, $today->startOfMonth(), $today, $now)
                ->map(fn (Collection $days): AttendanceSummary => AttendanceSummary::fromDays($days)),
        );

        return Inertia::render('Dashboard', [
            'dateLabel' => ucfirst($now->translatedFormat('l j F Y')),
            'updatedAt' => $now->format('H:i'),
            'rows' => $rows->map(fn (array $row): array => [
                'employee' => (new EmployeeResource($row['employee']))->resolve(),
                'day' => $row['day']->toArray(),
            ])->all(),
            'counts' => [
                'onSite' => $todayDays->filter(fn (DailyAttendance $day): bool => $day->status->isOnSite())->count(),
                'onBreak' => $todayDays->where('status', AttendanceStatus::OnBreak)->count(),
                'late' => $todayDays->filter(fn (DailyAttendance $day): bool => $day->isLate())->count(),
                'absent' => $todayDays->where('status', AttendanceStatus::Absent)->count(),
                'expected' => $todayDays->where('status', AttendanceStatus::Expected)->count(),
                'onLeave' => $todayDays->where('status', AttendanceStatus::OnLeave)->count(),
                'scheduled' => $todayDays->filter(fn (DailyAttendance $day): bool => $day->isScheduled())->count(),
                'arrived' => $todayDays->filter(fn (DailyAttendance $day): bool => $day->countsAsWorked())->count(),
                'awaited' => $todayDays->filter(fn (DailyAttendance $day): bool => $this->isAwaitedToday($day))->count(),
                'team' => $todayDays->count(),
            ],
            'departments' => $this->departmentBreakdown($rows),
            'chart' => $this->weekChart($week, $today),
            'monthSummary' => $monthSummary->toArray(),
            'recentPunches' => PunchResource::collection(
                Punch::query()
                    ->with('user')
                    ->whereDate('punched_at', $today)
                    ->latest('punched_at')
                    ->limit(8)
                    ->get(),
            )->resolve(),
            'pendingLeaves' => LeaveRequestResource::collection(
                LeaveRequest::query()
                    ->with('user')
                    ->where('status', LeaveStatus::Pending)
                    ->oldest('starts_on')
                    ->limit(4)
                    ->get(),
            )->resolve(),
            'kiosks' => Kiosk::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Kiosk $kiosk): array => [
                    'name' => $kiosk->name,
                    'url' => route('kiosk.show', $kiosk),
                ])
                ->all(),
        ]);
    }

    /**
     * Someone counts as awaited today when they are scheduled (and not on leave) or already came in.
     */
    private function isAwaitedToday(DailyAttendance $day): bool
    {
        return $day->countsAsWorked() || ($day->isScheduled() && $day->status !== AttendanceStatus::OnLeave);
    }

    /**
     * @param  Collection<int, array{employee: User, day: DailyAttendance}>  $rows
     * @return list<array{name: string, team: int, awaited: int, arrived: int, onSite: int}>
     */
    private function departmentBreakdown(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row): string => $row['employee']->department->name ?? 'Sans département')
            ->map(fn (Collection $group, string $name): array => [
                'name' => $name,
                'team' => $group->count(),
                'awaited' => $group->filter(fn (array $row): bool => $this->isAwaitedToday($row['day']))->count(),
                'arrived' => $group->filter(fn (array $row): bool => $row['day']->countsAsWorked())->count(),
                'onSite' => $group->filter(fn (array $row): bool => $row['day']->status->isOnSite())->count(),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<string, DailyAttendance>>  $week
     * @return list<array{label: string, present: int, late: int, absent: int, expected: int}>
     */
    private function weekChart(Collection $week, CarbonImmutable $today): array
    {
        $chart = [];

        for ($offset = 6; $offset >= 0; $offset--) {
            $date = $today->subDays($offset);
            $days = $week->map(fn (Collection $userDays): DailyAttendance => $userDays->get($date->toDateString()));

            $chart[] = [
                'label' => ucfirst($date->translatedFormat('D j')),
                'present' => $days->filter(fn (DailyAttendance $day): bool => $day->countsAsWorked())->count(),
                'late' => $days->filter(fn (DailyAttendance $day): bool => $day->isLate())->count(),
                'absent' => $days->where('status', AttendanceStatus::Absent)->count(),
                'expected' => $days->filter(fn (DailyAttendance $day): bool => $day->isScheduled() && $day->status !== AttendanceStatus::OnLeave)->count(),
            ];
        }

        return $chart;
    }
}
