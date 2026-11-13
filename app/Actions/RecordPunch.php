<?php

namespace App\Actions;

use App\Enums\PunchType;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\User;
use App\Support\AttendanceDay;
use App\Support\KioskToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPunch
{
    public function __construct(private KioskToken $tokens) {}

    public function handle(User $user, string $token, PunchType $type): Punch
    {
        $resolved = $this->tokens->resolve($token);

        return $this->store($user, $type, $resolved, now()->toImmutable(), [
            'is_offline' => false,
        ]);
    }

    /**
     * Records a punch typed on the tablet itself by an employee who has no phone, after checking their password.
     */
    public function handleAtKiosk(User $user, Kiosk $kiosk, PunchType $type): Punch
    {
        $resolved = ['kiosk' => $kiosk, 'window' => $this->tokens->window()];

        return $this->store($user, $type, $resolved, now()->toImmutable(), [
            'is_offline' => false,
            'via_kiosk_login' => true,
        ]);
    }

    /**
     * Records a punch made while the phone had no network. The scan time reported by the phone is only
     * trusted within the validity window of the code it scanned, so it cannot be moved freely.
     */
    public function handleOffline(User $user, string $uuid, string $token, PunchType $type, CarbonImmutable $scannedAt): Punch
    {
        $toleratedWindows = intdiv((int) config('pointage.offline_sync_hours') * 3600, $this->tokens->windowSeconds());
        $resolved = $this->tokens->resolve($token, toleratedPastWindows: $toleratedWindows);

        $earliest = $this->tokens->windowStart($resolved['window']);
        $latest = $earliest->addSeconds($this->tokens->windowSeconds() * 2)->min(now()->toImmutable());
        $punchedAt = $scannedAt->max($earliest)->min($latest);

        return $this->store($user, $type, $resolved, $punchedAt, [
            'uuid' => $uuid,
            'is_offline' => true,
            'synced_at' => now(),
        ]);
    }

    /**
     * @param  array{kiosk: Kiosk, window: int}  $resolved
     * @param  array<string, mixed>  $attributes
     */
    private function store(User $user, PunchType $type, array $resolved, CarbonImmutable $punchedAt, array $attributes): Punch
    {
        if ($resolved['kiosk']->shop_id !== $user->shop_id) {
            throw ValidationException::withMessages([
                'token' => 'Ce code appartient à une autre boutique. Scannez la borne de votre boutique.',
            ]);
        }

        return DB::transaction(function () use ($user, $type, $resolved, $punchedAt, $attributes): Punch {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $day = AttendanceDay::at($user, $punchedAt);

            if (! in_array($type, $day->allowedTypes(), true)) {
                throw ValidationException::withMessages([
                    'type' => $type === PunchType::Arrival && $day->closedReason !== null
                        ? $day->closedReason
                        : 'Cette action n\'est pas disponible pour le moment.',
                ]);
            }

            return Punch::query()->create([
                ...$attributes,
                'user_id' => $user->id,
                'kiosk_id' => $resolved['kiosk']->id,
                'type' => $type,
                'punched_at' => $punchedAt,
                'qr_window' => $resolved['window'],
            ]);
        });
    }
}
