<?php

namespace App\Actions;

use App\Enums\PunchType;
use App\Models\Punch;
use App\Models\PunchCorrection;
use App\Models\User;
use App\Support\AttendanceDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manager corrections on punches. Every change is logged with its reason, and the day must stay
 * a coherent sequence (arrival, breaks, departure) once the change is applied.
 */
class CorrectPunches
{
    public function add(User $employee, User $author, CarbonImmutable $at, PunchType $type, string $reason): Punch
    {
        $this->ensureNotInFuture($at);

        return DB::transaction(function () use ($employee, $author, $at, $type, $reason): Punch {
            $this->lock($employee);

            $punch = Punch::query()->create([
                'user_id' => $employee->id,
                'type' => $type,
                'punched_at' => $at,
                'is_manual' => true,
            ]);

            $this->ensureCoherentDay($employee, $at);
            $this->log($punch, $author, PunchCorrection::ACTION_ADDED, null, $punch->snapshot(), $reason);

            return $punch;
        });
    }

    public function update(Punch $punch, User $author, string $time, PunchType $type, string $reason): Punch
    {
        $at = CarbonImmutable::instance($punch->punched_at)->setTimeFromTimeString($time);
        $this->ensureNotInFuture($at);

        return DB::transaction(function () use ($punch, $author, $at, $type, $reason): Punch {
            $this->lock($punch->user);
            $before = $punch->snapshot();

            $punch->update(['type' => $type, 'punched_at' => $at]);

            $this->ensureCoherentDay($punch->user, $at);
            $this->log($punch, $author, PunchCorrection::ACTION_UPDATED, $before, $punch->snapshot(), $reason);

            return $punch;
        });
    }

    public function delete(Punch $punch, User $author, string $reason): void
    {
        DB::transaction(function () use ($punch, $author, $reason): void {
            $this->lock($punch->user);
            $before = $punch->snapshot();

            $punch->delete();

            $this->ensureCoherentDay($punch->user, CarbonImmutable::instance($punch->punched_at));
            $this->log($punch, $author, PunchCorrection::ACTION_DELETED, $before, null, $reason);
        });
    }

    private function ensureCoherentDay(User $employee, CarbonImmutable $date): void
    {
        $types = Punch::query()
            ->where('user_id', $employee->id)
            ->whereDate('punched_at', $date->toDateString())
            ->orderBy('punched_at')
            ->orderBy('id')
            ->pluck('type');

        $day = new AttendanceDay(null);

        foreach ($types as $type) {
            if (! in_array($type, $day->allowedTypes(), true)) {
                $previous = $day->lastType?->label() ?? 'le début de la journée';

                throw ValidationException::withMessages([
                    'punch' => "Journée incohérente après correction : « {$type->label()} » ne peut pas suivre « {$previous} ». Vérifiez l'ordre et les heures.",
                ]);
            }

            $day = new AttendanceDay($type);
        }
    }

    private function ensureNotInFuture(CarbonImmutable $at): void
    {
        if ($at->isAfter(now())) {
            throw ValidationException::withMessages([
                'time' => 'Un pointage ne peut pas être placé dans le futur.',
            ]);
        }
    }

    private function lock(User $employee): void
    {
        User::query()->whereKey($employee->id)->lockForUpdate()->first();
    }

    /**
     * @param  array<string, string>|null  $before
     * @param  array<string, string>|null  $after
     */
    private function log(Punch $punch, User $author, string $action, ?array $before, ?array $after, string $reason): void
    {
        PunchCorrection::query()->create([
            'punch_id' => $punch->id,
            'user_id' => $punch->user_id,
            'author_id' => $author->id,
            'punch_date' => $punch->punched_at->toDateString(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }
}
