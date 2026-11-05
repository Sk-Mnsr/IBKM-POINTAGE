<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * The shop a manager is currently working on. Managers of several shops can also look at all of them
 * at once; every manager screen is limited to the shops returned by scopeIds().
 */
class ShopContext
{
    private const string SESSION_KEY = 'current_shop_id';

    private ?Collection $accessibleShops = null;

    public function __construct(private Request $request) {}

    /**
     * @return Collection<int, Shop>
     */
    public function accessibleShops(): Collection
    {
        if ($this->accessibleShops !== null) {
            return $this->accessibleShops;
        }

        $user = $this->user();

        return $this->accessibleShops = $user === null
            ? new Collection
            : Shop::query()->whereKey($user->accessibleShopIds())->orderBy('name')->get();
    }

    /**
     * The selected shop, or null when every accessible shop is shown.
     */
    public function current(): ?Shop
    {
        $shops = $this->accessibleShops();

        if ($shops->count() === 1) {
            return $shops->first();
        }

        $selected = $this->request->hasSession() ? $this->request->session()->get(self::SESSION_KEY) : null;

        return $selected === null ? null : $shops->firstWhere('id', (int) $selected);
    }

    /**
     * @return list<int>
     */
    public function scopeIds(): array
    {
        $current = $this->current();

        return $current === null ? $this->accessibleShops()->modelKeys() : [$current->id];
    }

    public function isMultiShop(): bool
    {
        return count($this->scopeIds()) > 1;
    }

    public function select(?int $shopId): void
    {
        if ($shopId !== null && ! $this->accessibleShops()->contains('id', $shopId)) {
            abort(403);
        }

        $this->request->session()->put(self::SESSION_KEY, $shopId);
    }

    /**
     * The shop new records go into when the form does not let the user pick one.
     */
    public function defaultShopId(): ?int
    {
        return $this->current()?->id ?? $this->accessibleShops()->first()?->id;
    }

    private function user(): ?User
    {
        $user = $this->request->user();

        return $user instanceof User ? $user : null;
    }
}
