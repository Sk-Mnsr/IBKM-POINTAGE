<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * Senegalese public holidays on a fixed date (month-day).
     */
    public const array FIXED_DATES = [
        '01-01' => 'Jour de l’An',
        '04-04' => 'Fête de l’Indépendance',
        '05-01' => 'Fête du Travail',
        '08-15' => 'Assomption',
        '11-01' => 'Toussaint',
        '12-25' => 'Noël',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }

    /**
     * Holidays whose date can be computed for a year: fixed dates and those following Easter.
     * Tabaski, Korité, Tamkharit, Maouloud and the Magal follow the lunar calendar and are added by hand.
     *
     * @return array<string, string> date (Y-m-d) => name
     */
    public static function suggestionsFor(int $year): array
    {
        $suggestions = [];

        foreach (self::FIXED_DATES as $monthDay => $name) {
            $suggestions["{$year}-{$monthDay}"] = $name;
        }

        $easter = self::easterSunday($year);
        $suggestions[$easter->addDay()->toDateString()] = 'Lundi de Pâques';
        $suggestions[$easter->addDays(39)->toDateString()] = 'Ascension';
        $suggestions[$easter->addDays(50)->toDateString()] = 'Lundi de Pentecôte';

        ksort($suggestions);

        return $suggestions;
    }

    /**
     * Gregorian Easter Sunday (anonymous algorithm), without relying on the calendar extension.
     */
    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
