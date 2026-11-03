<?php

namespace App\Models;

use Database\Factories\KioskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'public_id', 'secret', 'is_active', 'pairing_version'])]
#[Hidden(['secret'])]
class Kiosk extends Model
{
    /** @use HasFactory<KioskFactory> */
    use HasFactory;

    protected $attributes = [
        'pairing_version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pairing_version' => 'integer',
        ];
    }

    public function pairingCookieName(): string
    {
        return 'kiosk_pairing_'.$this->public_id;
    }

    public function pairingCookieValue(): string
    {
        return hash_hmac('sha256', $this->public_id.'|'.$this->pairing_version, $this->secret);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return HasMany<Punch, $this>
     */
    public function punches(): HasMany
    {
        return $this->hasMany(Punch::class);
    }
}
