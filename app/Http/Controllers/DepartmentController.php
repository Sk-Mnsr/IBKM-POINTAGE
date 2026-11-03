<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Departments/Index', [
            'departments' => DepartmentResource::collection(
                Department::query()->withCount('users')->orderBy('name')->get(),
            )->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Departments/Form', [
            'department' => null,
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::query()->create($request->safe()->only(['name']));

        return redirect()->route('departments.index')->with('status', 'Département créé.');
    }

    public function edit(Department $department): Response
    {
        return Inertia::render('Departments/Form', [
            'department' => (new DepartmentResource($department))->resolve(),
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
