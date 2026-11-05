<?php

namespace App\Support;

use App\Models\Kiosk;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class KioskToken
{
    /**
     * @return array{token: string, expires_in: int}
     */
    public function issue(Kiosk $kiosk, ?int $timestamp = null): array
    {
        $timestamp ??= now()->getTimestamp();
        $window = $this->window($timestamp);
        $token = $this->compile($kiosk, $window);
        $windowSeconds = $this->windowSeconds();

        return [
            'token' => $token,
            'expires_in' => $windowSeconds - ($timestamp % $windowSeconds),
        ];
    }

    /**
     * Pre-signed codes for the coming hours, so the tablet keeps showing valid codes without a connection.
     *
     * @return list<array{window: int, token: string}>
     */
    public function batch(Kiosk $kiosk, int $hours, ?int $timestamp = null): array
    {
        $current = $this->window($timestamp ?? now()->getTimestamp());
        $last = $current + intdiv($hours * 3600, $this->windowSeconds());
        $tokens = [];

        for ($window = $current; $window <= $last; $window++) {
            $tokens[] = ['window' => $window, 'token' => $this->compile($kiosk, $window)];
        }

        return $tokens;
    }

    /**
     * Accepts the current window, one window of clock drift ahead, and $toleratedPastWindows behind.
     *
     * @return array{kiosk: Kiosk, window: int}
     */
    public function resolve(string $token, ?int $timestamp = null, int $toleratedPastWindows = 1): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 4 || $parts[0] !== '1' || ! ctype_digit($parts[2]) || strlen($parts[3]) !== 32) {
            throw ValidationException::withMessages([
                'token' => 'Ce code est illisible. Scannez le QR affiché sur la tablette.',
            ]);
        }

        [, $publicId, $window, $signature] = $parts;
        $window = (int) $window;

        $kiosk = Kiosk::query()
            ->where('public_id', $publicId)
            ->where('is_active', true)
            ->whereHas('shop', fn ($shops) => $shops->where('is_active', true))
            ->first();

        if ($kiosk === null || ! hash_equals($this->sign($kiosk, $window), $signature)) {
            throw ValidationException::withMessages([
                'token' => 'Ce code ne correspond pas à la borne du salon.',
            ]);
        }

        $currentWindow = $this->window($timestamp ?? now()->getTimestamp());

        if ($window > $currentWindow + 1 || $window < $currentWindow - $toleratedPastWindows) {
            throw ValidationException::withMessages([
                'token' => 'Ce code a expiré. Scannez le code actuellement affiché sur la tablette.',
            ]);
        }

        return [
            'kiosk' => $kiosk,
            'window' => $window,
        ];
    }

    public function window(?int $timestamp = null): int
    {
        $timestamp ??= now()->getTimestamp();

        return intdiv($timestamp, $this->windowSeconds());
    }

    public function windowStart(int $window): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($window * $this->windowSeconds(), config('app.timezone'));
    }

    public function windowSeconds(): int
    {
        return max(1, (int) config('pointage.qr_window_seconds'));
    }

    private function compile(Kiosk $kiosk, int $window): string
    {
        return '1.'.$kiosk->public_id.'.'.$window.'.'.$this->sign($kiosk, $window);
    }

    private function sign(Kiosk $kiosk, int $window): string
    {
        return substr(hash_hmac('sha256', $kiosk->public_id.'|'.$window, $kiosk->secret), 0, 32);
    }
}
