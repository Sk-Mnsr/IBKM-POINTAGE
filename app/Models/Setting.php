<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A value edited from the settings page, overriding the matching key of config/pointage.php.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Settings the super admin may change, mapped to their config key.
     */
    public const array EDITABLE = [
        'institute_name' => 'pointage.institute_name',
        'late_tolerance_minutes' => 'pointage.late_tolerance_minutes',
    ];

    private const string CACHE_KEY = 'pointage.settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Loads the saved values into the configuration. Before the first migration the table does not exist yet:
     * the defaults of config/pointage.php then stay in place.
     */
    public static function applyToConfig(): void
    {
        try {
            $values = Cache::rememberForever(self::CACHE_KEY, fn (): array => self::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return;
        }

        foreach (self::EDITABLE as $key => $configKey) {
            if (isset($values[$key])) {
                config([$configKey => $values[$key]]);
            }
        }
    }

    /**
     * @param  array<string, string|int>  $values
     */
    public static function store(array $values): void
    {
        foreach (array_intersect_key($values, self::EDITABLE) as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }
}
