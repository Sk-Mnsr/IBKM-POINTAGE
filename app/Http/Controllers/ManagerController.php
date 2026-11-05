<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreManagerRequest;
use App\Http\Requests\UpdateManagerRequest;
use App\Http\Resources\ManagerResource;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ManagerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Managers/Index', [
            'managers' => ManagerResource::collection(
                User::query()
                    ->managers()
                    ->with(['shops' => fn ($shops) => $shops->orderBy('name')])
                    ->orderByRaw('case when role = ? then 0 else 1 end', [UserRole::SuperAdmin->value])
                    ->orderBy('name')
                    ->get(),
            )->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Managers/Form', [
            'manager' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreManagerRequest $request): RedirectResponse
    {
        $manager = DB::transaction(function () use ($request): User {
            $manager = User::query()->create([
                'name' => $request->string('name')->toString(),
                'email' => $request->validated('email'),
                'phone' => $request->string('phone')->toString(),
                'password' => $request->string('password')->toString(),
                'role' => UserRole::from($request->string('role')->toString()),
                'is_active' => $request->boolean('is_active'),
                'must_change_password' => $request->boolean('must_change_password', true),
            ]);

            $manager->shops()->sync($request->validated('shop_ids', []));

            return $manager;
        });

        return redirect()->route('managers.index')->with('status', "{$manager->name} a été ajouté(e).");
    }

    public function edit(User $manager): Response
    {
        return Inertia::render('Managers/Form', [
            'manager' => (new ManagerResource($manager->load('shops')))->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateManagerRequest $request, User $manager): RedirectResponse
    {
        DB::transaction(function () use ($request, $manager): void {
            $manager->fill([
                'name' => $request->string('name')->toString(),
                'email' => $request->validated('email'),
                'phone' => $request->string('phone')->toString(),
                'role' => UserRole::from($request->string('role')->toString()),
                'is_active' => $request->boolean('is_active'),
            ]);

            if ($request->filled('password')) {
                $manager->password = $request->string('password')->toString();
                $manager->must_change_password = $request->boolean('must_change_password', true);
            }

            $manager->save();
            $manager->shops()->sync($request->validated('shop_ids', []));
        });

        return redirect()->route('managers.index')->with('status', 'Modifications enregistrées.');
    }

    /**
     * @return array{shops: list<array{id: int, name: string, isActive: bool}>, roles: list<array{value: string, label: string, description: string}>}
     */
    private function formOptions(): array
    {
        return [
            'shops' => Shop::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Shop $shop): array => ['id' => $shop->id, 'name' => $shop->name, 'isActive' => $shop->is_active])
                ->all(),
            'roles' => [
                [
                    'value' => UserRole::Admin->value,
                    'label' => UserRole::Admin->label(),
                    'description' => 'Gère uniquement les boutiques cochées ci-dessous.',
                ],
                [
                    'value' => UserRole::SuperAdmin->value,
                    'label' => UserRole::SuperAdmin->label(),
                    'description' => 'Voit toutes les boutiques, crée les boutiques et les gérants.',
                ],
            ],
        ];
    }
}
