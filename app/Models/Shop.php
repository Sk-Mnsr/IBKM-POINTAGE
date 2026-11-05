<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'address', 'phone', 'is_active'])]
class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory;

    public const array DEFAULT_DEPARTMENTS = ['Coiffure', 'Esthétique', 'Onglerie', 'Caisse'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * A new shop starts with the usual departments and a reception kiosk.
     */
    public function provisionDefaults(): void
    {
        foreach (self::DEFAULT_DEPARTMENTS as $name) {
            $this->departments()->firstOrCreate(['name' => $name]);
        }

        if (! $this->kiosks()->exists()) {
            $this->kiosks()->create([
                'name' => 'Accueil',
                'public_id' => (string) Str::ulid(),
                'secret' => Str::random(40),
            ]);
        }
    }

    /**
     * @return HasMany<User, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class)->where('role', UserRole::Staff);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return HasMany<Kiosk, $this>
     */
    public function kiosks(): HasMany
    {
        return $this->hasMany(Kiosk::class);
    }
}
