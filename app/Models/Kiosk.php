<?php

namespace App\Models;

use Database\Factories\KioskFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;

#[Fillable(['shop_id', 'name', 'public_id', 'secret', 'is_active', 'pairing_version'])]
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

    /**
     * A kiosk only shows codes while it is active and its shop is open.
     */
    public function isOpen(): bool
    {
        return $this->is_active && $this->shop?->is_active !== false;
    }

    /**
     * The tablet may show codes and take punches when a manager of the shop is signed in on it, or once it has been paired.
     */
    public function canBeOperatedFrom(Request $request): bool
    {
        return $this->isManagedBy($request->user()) || $this->isPairedWith($request);
    }

    public function isManagedBy(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->isAdmin() && $user->canAccessShop($this->shop_id);
    }

    public function isPairedWith(Request $request): bool
    {
        $value = $request->cookie($this->pairingCookieName());

        return is_string($value) && hash_equals($this->pairingCookieValue(), $value);
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

    /**
     * @return HasMany<CustomerReview, $this>
     */
    public function customerReviews(): HasMany
    {
        return $this->hasMany(CustomerReview::class);
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
