<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use App\Models\Shop;
use App\Support\ShopContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(private ShopContext $shops) {}

    public function index(): Response
    {
        return Inertia::render('Departments/Index', [
            'departments' => DepartmentResource::collection(
                Department::query()
                    ->whereIn('shop_id', $this->shops->scopeIds())
                    ->with('shop')
                    ->withCount('users')
                    ->orderBy('name')
                    ->get(),
            )->resolve(),
            'showShop' => $this->shops->isMultiShop(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Departments/Form', [
            'department' => null,
            'shops' => $this->shops->accessibleShops()
                ->map(fn (Shop $shop): array => ['id' => $shop->id, 'name' => $shop->name])
                ->values()
                ->all(),
            'defaultShopId' => $this->shops->defaultShopId(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->safe()->only(['shop_id', 'name']));

        return redirect()->route('departments.index')->with('status', 'Département créé.');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('Departments/Form', [
            'department' => (new DepartmentResource($department->load('shop')))->resolve(),
            'shops' => [],
            'defaultShopId' => $department->shop_id,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->safe()->only(['name']));

        return redirect()->route('departments.index')->with('status', 'Département renommé.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists()) {
            return redirect()->route('departments.index')->withErrors([
                'name' => 'Ce département a encore des employés.',
            ]);
        }

        $department->delete();

        return redirect()->route('departments.index')->with('status', 'Département supprimé.');
    }
}
