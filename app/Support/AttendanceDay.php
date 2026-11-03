<?php

namespace App\Support;

use App\Enums\PunchType;
use App\Models\Punch;
use App\Models\User;
use Carbon\CarbonInterface;

class AttendanceDay
{
    public function __construct(public ?PunchType $lastType) {}

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

        return new self($lastPunch?->type);
    }

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
            null, PunchType::Departure => [PunchType::Arrival],
            PunchType::Arrival, PunchType::BreakEnd => [PunchType::BreakStart, PunchType::Departure],
            PunchType::BreakStart => [PunchType::BreakEnd],
        };
    }
}
