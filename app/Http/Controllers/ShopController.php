<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Shops/Index', [
            'shops' => ShopResource::collection(
                Shop::query()
                    ->with([
                        'managers' => fn ($managers) => $managers->orderBy('name'),
                        'kiosks' => fn ($kiosks) => $kiosks->where('is_active', true)->orderBy('name'),
                    ])
                    ->withCount(['staff' => fn ($staff) => $staff->where('is_active', true), 'kiosks'])
                    ->orderBy('name')
                    ->get(),
            )->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Shops/Form', [
            'shop' => null,
            'defaultDepartments' => Shop::DEFAULT_DEPARTMENTS,
        ]);
    }

    public function store(StoreShopRequest $request): RedirectResponse
    {
        $shop = DB::transaction(function () use ($request): Shop {
            $shop = Shop::query()->create($request->validated());
            $shop->provisionDefaults();

            return $shop;
        });

        return redirect()
            ->route('shops.index')
            ->with('status', "La boutique {$shop->name} a été créée avec ses départements et sa borne d'accueil.");
    }

    public function edit(Shop $shop): Response
    {
        $shop->load([
            'managers' => fn ($managers) => $managers->orderBy('name'),
            'kiosks' => fn ($kiosks) => $kiosks->orderByDesc('is_active')->orderBy('name'),
            'departments' => fn ($departments) => $departments
                ->withCount(['users' => fn ($users) => $users->where('is_active', true)])
                ->orderBy('name'),
        ])->loadCount(['staff' => fn ($staff) => $staff->where('is_active', true), 'kiosks']);

        return Inertia::render('Shops/Form', [
            'shop' => (new ShopResource($shop))->resolve(),
            'defaultDepartments' => Shop::DEFAULT_DEPARTMENTS,
        ]);
    }

    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $shop->update($request->validated());

        return redirect()->route('shops.index')->with('status', 'Modifications enregistrées.');
    }
}
