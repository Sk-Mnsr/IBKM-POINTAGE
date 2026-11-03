<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'weekday', 'starts_at', 'ends_at'])]
class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use HasFactory;

    /**
     * @return array<int, string>
     */
    public static function weekdayLabels(): array
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ];
    }

    public function formattedStart(): string
    {
        return substr((string) $this->starts_at, 0, 5);
    }

    public function formattedEnd(): string
    {
        return substr((string) $this->ends_at, 0, 5);
    }

    /**
     * Minutes of lateness for an arrival on this schedule's day, counted in full once past the tolerance.
     */
    public function lateMinutesFor(CarbonInterface $arrival): int
    {
        $start = CarbonImmutable::instance($arrival)->setTimeFromTimeString($this->formattedStart());
        $delay = (int) floor($start->diffInMinutes($arrival, false));

        return $delay > (int) config('pointage.late_tolerance_minutes') ? $delay : 0;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
