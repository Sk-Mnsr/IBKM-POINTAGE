<?php

namespace App\Support;

use App\Enums\PunchType;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class AttendanceDay
{
    /**
     * @param  string|null  $closedReason  Why the employee cannot start a day (rest day, leave), if so.
     */
    public function __construct(public ?PunchType $lastType, public ?string $closedReason = null) {}

    public static function for(User $user): self
    {
        return self::at($user, now());
    }

    /**
     * State of the user's day just before the given moment, for punches synchronised after the fact.
     */
    public static function at(User $user, CarbonInterface $moment): self
    {
        $lastPunch = $user->punches()
            ->whereDate('punched_at', $moment->toDateString())
            ->where('punched_at', '<=', $moment)
            ->latest('punched_at')
            ->first();

        return new self($lastPunch?->type, self::closedReason($user, $moment));
    }

    /**
     * Manager corrections are not limited by the planning: they may record exceptional work.
     */
    public static function fromPunch(?Punch $punch): self
    {
        return new self($punch?->type);
    }

    public function statusLabel(): string
    {
        return match ($this->lastType) {
            null => 'Pas arrivé',
            PunchType::Arrival, PunchType::BreakEnd => 'Présent',
            PunchType::BreakStart => 'En pause',
            PunchType::Departure => 'Parti',
        };
    }

    /**
     * @return list<PunchType>
     */
    public function allowedTypes(): array
    {
        return match ($this->lastType) {
            null, PunchType::Departure => $this->closedReason === null ? [PunchType::Arrival] : [],
            PunchType::Arrival, PunchType::BreakEnd => [PunchType::BreakStart, PunchType::Departure],
            PunchType::BreakStart => [PunchType::BreakEnd],
        };
    }

    /**
     * An employee whose planning has not been entered yet is never blocked.
     */
    private static function closedReason(User $user, CarbonInterface $moment): ?string
    {
        $day = CarbonImmutable::instance($moment)->startOfDay();

        $isOnLeave = LeaveRequest::query()
            ->approved()
            ->where('user_id', $user->id)
            ->overlapping($day, $day)
            ->exists();

        if ($isOnLeave) {
            return 'Vous êtes en congé ce jour-là : le pointage n’est pas possible.';
        }

        $workedWeekdays = $user->workSchedules()->pluck('weekday');

        if ($workedWeekdays->isNotEmpty() && ! $workedWeekdays->contains($day->dayOfWeekIso)) {
            return 'C’est un jour de repos dans votre planning : le pointage n’est pas possible.';
        }

        return null;
    }
}
