<?php

namespace App\Support;

class Duration
{
    public static function format(int $minutes, string $empty = '—'): string
    {
        if ($minutes <= 0) {
            return $empty;
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return "{$rest} min";
        }

        return sprintf('%d h %02d', $hours, $rest);
    }

    public static function decimalHours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }
}
