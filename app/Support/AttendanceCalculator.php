<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    /**
     * Daily attendance for each user over a date range, keyed by user id then by date (Y-m-d).
     *
     * @param  EloquentCollection<int, User>  $users
     * @return Collection<int, Collection<string, DailyAttendance>>
     */
    public function forPeriod(EloquentCollection $users, CarbonImmutable $start, CarbonImmutable $end, ?CarbonImmutable $now = null): Collection
    {
        $now ??= CarbonImmutable::now();
        $start = $start->startOfDay();
        $end = $end->endOfDay();
        $userIds = $users->modelKeys();

        $users->loadMissing('workSchedules');

        $punches = Punch::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('punched_at', [$start, $end])
            ->orderBy('punched_at')
            ->get()
            ->groupBy(fn (Punch $punch): string => $punch->user_id.'|'.$punch->punched_at->toDateString());

        $leaves = LeaveRequest::query()
            ->approved()
            ->whereIn('user_id', $userIds)
            ->overlapping($start, $end)
            ->get()
            ->groupBy('user_id');

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $dates = collect(CarbonPeriod::create($start->startOfDay(), '1 day', $end->startOfDay()))
            ->map(fn ($date): CarbonImmutable => CarbonImmutable::instance($date));

        return $users->mapWithKeys(function (User $user) use ($dates, $punches, $leaves, $holidays, $now): array {
            $schedules = $user->workSchedules->keyBy('weekday');
            $userLeaves = $leaves->get($user->id, collect());

            $days = $dates->mapWithKeys(function (CarbonImmutable $date) use ($user, $schedules, $punches, $userLeaves, $holidays, $now): array {
                $key = $date->toDateString();

                return [$key => new DailyAttendance(
                    date: $date,
                    schedule: $schedules->get($date->dayOfWeekIso),
                    punches: $punches->get($user->id.'|'.$key, collect()),
                    leave: $userLeaves->first(fn (LeaveRequest $leave): bool => $leave->covers($date)),
                    now: $now,
                    holiday: $holidays->get($key),
                )];
            });

            return [$user->id => $days];
        });
    }

    /**
     * @param  EloquentCollection<int, User>  $users
     * @return Collection<int, DailyAttendance>
     */
    public function forDay(EloquentCollection $users, CarbonImmutable $date, ?CarbonImmutable $now = null): Collection
    {
        return $this->forPeriod($users, $date, $date, $now)
            ->map(fn (Collection $days): DailyAttendance => $days->first());
    }

    /**
     * @return Collection<string, DailyAttendance>
     */
    public function forUser(User $user, CarbonImmutable $start, CarbonImmutable $end, ?CarbonImmutable $now = null): Collection
    {
        return $this->forPeriod(new EloquentCollection([$user]), $start, $end, $now)->get($user->id);
    }
}
