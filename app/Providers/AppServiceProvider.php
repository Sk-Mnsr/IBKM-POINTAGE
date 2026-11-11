<?php

namespace App\Providers;

use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceHttps($this->app->isProduction());
        Setting::applyToConfig();

        $this->bindWithinManagedShops();
    }

    /**
     * A manager only reaches the records of the shops they manage: anything else is a 404,
     * which does not reveal that the record exists.
     */
    private function bindWithinManagedShops(): void
    {
        $managedShopIds = function (): ?array {
            $user = request()->user();

            return $user instanceof User && $user->isAdmin() ? $user->accessibleShopIds() : null;
        };

        Route::bind('employee', function (string $value) use ($managedShopIds): User {
            return User::query()
                ->staff()
                ->whereKey($value)
                ->when($managedShopIds(), fn (Builder $query, array $ids) => $query->whereIn('shop_id', $ids))
                ->firstOrFail();
        });

        Route::bind('manager', function (string $value): User {
            return User::query()->managers()->whereKey($value)->firstOrFail();
        });

        Route::bind('department', function (string $value) use ($managedShopIds): Department {
            return Department::query()
                ->whereKey($value)
                ->when($managedShopIds(), fn (Builder $query, array $ids) => $query->whereIn('shop_id', $ids))
                ->firstOrFail();
        });

        Route::bind('punch', function (string $value) use ($managedShopIds): Punch {
            return Punch::query()
                ->whereKey($value)
                ->when($managedShopIds(), fn (Builder $query, array $ids) => $query->whereHas(
                    'user',
                    fn (Builder $users) => $users->whereIn('shop_id', $ids),
                ))
                ->firstOrFail();
        });

        Route::bind('leaveRequest', function (string $value) use ($managedShopIds): LeaveRequest {
            return LeaveRequest::query()
                ->whereKey($value)
                ->when($managedShopIds(), fn (Builder $query, array $ids) => $query->whereHas(
                    'user',
                    fn (Builder $users) => $users->whereIn('shop_id', $ids),
                ))
                ->firstOrFail();
        });
    }
}
