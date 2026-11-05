<?php

namespace App\Models;

use App\Enums\PunchType;
use Database\Factories\PunchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'user_id', 'kiosk_id', 'type', 'punched_at', 'qr_window', 'is_offline', 'synced_at', 'is_manual', 'via_kiosk_login'])]
class Punch extends Model
{
    /** @use HasFactory<PunchFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Punch $punch): void {
            $punch->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PunchType::class,
            'punched_at' => 'datetime',
            'is_offline' => 'boolean',
            'is_manual' => 'boolean',
            'via_kiosk_login' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return array{type: string, label: string, time: string}
     */
    public function snapshot(): array
    {
        return [
            'type' => $this->type->value,
            'label' => $this->type->label(),
            'time' => $this->punched_at->format('H:i'),
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Kiosk, $this>
     */
    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    /**
     * @return HasMany<PunchCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(PunchCorrection::class);
    }
}
