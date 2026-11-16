<?php

namespace App\Http\Middleware;

use App\Enums\CustomerReviewRating;
use App\Enums\LeaveStatus;
use App\Models\CustomerReview;
use App\Models\LeaveRequest;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'instituteName' => config('pointage.institute_name'),
                'lateToleranceMinutes' => (int) config('pointage.late_tolerance_minutes'),
            ],
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'initials' => $user->initials(),
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                    'department' => $user->department?->name,
                    'shop' => $user->shop?->name,
                ],
            ],
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
            'shopContext' => fn (): ?array => $user?->isAdmin() ? $this->shopContext($request) : null,
            'pendingLeaveCount' => fn (): int => $user?->isAdmin()
                ? LeaveRequest::query()
                    ->where('status', LeaveStatus::Pending)
                    ->inShops(app(ShopContext::class)->scopeIds())
                    ->count()
                : 0,
            'unsatisfiedReviewCount' => fn (): int => $user?->isAdmin()
                ? CustomerReview::query()
                    ->whereIn('shop_id', app(ShopContext::class)->scopeIds())
                    ->where('rating', CustomerReviewRating::Unsatisfied)
                    ->whereBetween('created_at', [
                        CarbonImmutable::today()->startOfDay(),
                        CarbonImmutable::today()->endOfDay(),
                    ])
                    ->count()
                : 0,
        ];
    }

    /**
     * @return array{current: array{id: int, name: string}|null, shops: list<array{id: int, name: string}>}
     */
    private function shopContext(Request $request): array
    {
        $context = app(ShopContext::class);
        $current = $context->current();

        return [
            'current' => $current === null ? null : ['id' => $current->id, 'name' => $current->name],
            'shops' => $context->accessibleShops()
                ->map(fn (Shop $shop): array => ['id' => $shop->id, 'name' => $shop->name])
                ->values()
                ->all(),
        ];
    }
}
