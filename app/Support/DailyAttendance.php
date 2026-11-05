<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use App\Enums\PunchType;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DailyAttendance
{
    public ?CarbonImmutable $scheduledStart = null;

    public ?CarbonImmutable $scheduledEnd = null;

    public ?CarbonImmutable $firstArrival = null;

    public ?CarbonImmutable $lastDeparture = null;

    public int $scheduledMinutes = 0;

    public int $workedMinutes = 0;

    public int $breakMinutes = 0;

    public int $lateMinutes = 0;

    public int $overtimeMinutes = 0;

    public AttendanceStatus $status;

    /**
     * @param  Collection<int, Punch>  $punches
     */
    public function __construct(
        public CarbonImmutable $date,
        public ?WorkSchedule $schedule,
        public Collection $punches,
        public ?LeaveRequest $leave,
        CarbonImmutable $now,
    ) {
        $this->punches = $punches->sortBy('punched_at')->values();

        if ($schedule !== null) {
            $this->scheduledStart = $date->setTimeFromTimeString($schedule->formattedStart());
            $this->scheduledEnd = $date->setTimeFromTimeString($schedule->formattedEnd());
            $this->scheduledMinutes = (int) $this->scheduledStart->diffInMinutes($this->scheduledEnd);
        }

        $isToday = $date->isSameDay($now);
        $openSegment = $this->measure($isToday ? $now : null);

        if ($this->firstArrival !== null && $schedule !== null) {
            $this->lateMinutes = $schedule->lateMinutesFor($this->firstArrival);
        }

        $this->overtimeMinutes = max(0, $this->workedMinutes - $this->scheduledMinutes);
        $this->status = $this->resolveStatus($now, $isToday, $openSegment);
    }

    public function isLate(): bool
    {
        return $this->lateMinutes > 0;
    }

    public function isScheduled(): bool
    {
        return $this->schedule !== null;
    }

    public function lastPunch(): ?Punch
    {
        return $this->punches->last();
    }

    public function countsAsWorked(): bool
    {
        return $this->punches->isNotEmpty();
    }

    public function scheduleLabel(): string
    {
        if ($this->schedule === null) {
            return 'Repos';
        }

        return $this->schedule->formattedStart().' – '.$this->schedule->formattedEnd();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->toDateString(),
            'dateLabel' => ucfirst($this->date->translatedFormat('D d/m')),
            'weekdayShort' => $this->date->translatedFormat('D'),
            'weekdayLong' => ucfirst($this->date->translatedFormat('l')),
            'dayNumber' => $this->date->format('d'),
            'monthDay' => $this->date->translatedFormat('j M'),
            'isToday' => $this->date->isToday(),
            'isScheduled' => $this->isScheduled(),
            'scheduleLabel' => $this->scheduleLabel(),
            'firstArrival' => $this->firstArrival?->format('H:i'),
            'lastDeparture' => $this->lastDeparture?->format('H:i'),
            'scheduledMinutes' => $this->scheduledMinutes,
            'workedMinutes' => $this->workedMinutes,
            'breakMinutes' => $this->breakMinutes,
            'lateMinutes' => $this->lateMinutes,
            'overtimeMinutes' => $this->overtimeMinutes,
            'status' => $this->status->toArray(),
            'leave' => $this->leave === null ? null : ['type' => $this->leave->type->label()],
            'punches' => $this->punches
                ->map(fn (Punch $punch): array => [
                    'id' => $punch->id,
                    'type' => $punch->type->toArray(),
                    'time' => $punch->punched_at->format('H:i'),
                    'isManual' => $punch->is_manual,
                    'isOffline' => $punch->is_offline,
                    'viaKioskLogin' => $punch->via_kiosk_login,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Returns the type of segment still open at the end of the punches, if any.
     */
    private function measure(?CarbonImmutable $openUntil): ?PunchType
    {
        $workStartedAt = null;
        $breakStartedAt = null;

        foreach ($this->punches as $punch) {
            $at = CarbonImmutable::instance($punch->punched_at);

            switch ($punch->type) {
                case PunchType::Arrival:
                    $this->firstArrival ??= $at;
                    $workStartedAt = $at;
                    break;
                case PunchType::BreakStart:
                    if ($workStartedAt !== null) {
                        $this->workedMinutes += $this->minutesBetween($workStartedAt, $at);
                    }
                    $workStartedAt = null;
                    $breakStartedAt = $at;
                    break;
                case PunchType::BreakEnd:
                    if ($breakStartedAt !== null) {
                        $this->breakMinutes += $this->minutesBetween($breakStartedAt, $at);
                    }
                    $breakStartedAt = null;
                    $workStartedAt = $at;
                    break;
                case PunchType::Departure:
                    if ($workStartedAt !== null) {
                        $this->workedMinutes += $this->minutesBetween($workStartedAt, $at);
                    }
                    $workStartedAt = null;
                    $this->lastDeparture = $at;
                    break;
            }
        }

        if ($workStartedAt !== null) {
            if ($openUntil !== null) {
                $this->workedMinutes += $this->minutesBetween($workStartedAt, $openUntil);
            }

            return PunchType::Arrival;
        }

        if ($breakStartedAt !== null) {
            if ($openUntil !== null) {
                $this->breakMinutes += $this->minutesBetween($breakStartedAt, $openUntil);
            }

            return PunchType::BreakStart;
        }

        return null;
    }

    private function resolveStatus(CarbonImmutable $now, bool $isToday, ?PunchType $openSegment): AttendanceStatus
    {
        if ($this->punches->isNotEmpty()) {
            if ($isToday) {
                return match ($openSegment) {
                    PunchType::Arrival => AttendanceStatus::Present,
                    PunchType::BreakStart => AttendanceStatus::OnBreak,
                    default => AttendanceStatus::Left,
                };
            }

            return $openSegment === null ? AttendanceStatus::Worked : AttendanceStatus::Incomplete;
        }

        if ($this->schedule === null) {
            return AttendanceStatus::DayOff;
        }

        if ($this->leave !== null) {
            return AttendanceStatus::OnLeave;
        }

        if ($this->date->isAfter($now->startOfDay())) {
            return AttendanceStatus::Upcoming;
        }

        if ($isToday && $now->lessThanOrEqualTo($this->scheduledStart->addMinutes((int) config('pointage.late_tolerance_minutes')))) {
            return AttendanceStatus::Expected;
        }

        return AttendanceStatus::Absent;
    }

    private function minutesBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return max(0, (int) floor($from->diffInMinutes($to, false)));
    }
}
