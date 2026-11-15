<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'phone', 'password', 'department_id', 'shop_id', 'role', 'is_active', 'must_change_password', 'password_changed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function staff(Builder $query): Builder
    {
        return $query->where('role', UserRole::Staff);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function managers(Builder $query): Builder
    {
        return $query->whereIn('role', [UserRole::Admin, UserRole::SuperAdmin]);
    }

    /**
     * Manager of at least one shop (shop manager or super admin).
     */
    public function isAdmin(): bool
    {
        return $this->role->isManager();
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Staff;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /**
     * Shops this user may see and manage. A super admin sees every shop, closed ones included.
     *
     * @return list<int>
     */
    public function accessibleShopIds(): array
    {
        return match (true) {
            $this->isSuperAdmin() => Shop::query()->orderBy('name')->pluck('id')->all(),
            $this->role === UserRole::Admin => $this->shops()->where('is_active', true)->orderBy('name')->pluck('shops.id')->all(),
            default => $this->shop_id === null ? [] : [$this->shop_id],
        };
    }

    public function canAccessShop(?int $shopId): bool
    {
        return $shopId !== null && in_array($shopId, $this->accessibleShopIds(), true);
    }

    public function givenName(): string
    {
        return Str::before($this->name, ' ');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Shop a staff member works in.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Shops a manager is in charge of.
     *
     * @return BelongsToMany<Shop, $this>
     */
    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class)->withTimestamps();
    }

    /**
     * @return HasMany<WorkSchedule, $this>
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $schedules
     */
    public function syncWorkSchedules(array $schedules): void
    {
        foreach (array_keys(WorkSchedule::weekdayLabels()) as $weekday) {
            $row = $schedules[$weekday] ?? [];
            $start = is_array($row) ? (string) ($row['starts_at'] ?? '') : '';
            $end = is_array($row) ? (string) ($row['ends_at'] ?? '') : '';

            if ($start === '' || $end === '') {
                $this->workSchedules()->where('weekday', $weekday)->delete();

                continue;
            }

            $this->workSchedules()->updateOrCreate(
                ['weekday' => $weekday],
                ['starts_at' => $start, 'ends_at' => $end],
            );
        }
    }

    /**
     * @return HasMany<Punch, $this>
     */
    public function punches(): HasMany
    {
        return $this->hasMany(Punch::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
