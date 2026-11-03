<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Employees/Index', [
            'employees' => EmployeeResource::collection(
                User::query()->staff()->with(['department', 'workSchedules'])->withCount('workSchedules')->orderBy('name')->get(),
            )->resolve(),
            'departments' => DepartmentResource::collection(Department::query()->orderBy('name')->get())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Form', [
            'employee' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->validated('email'),
            'phone' => $request->string('phone')->toString(),
            'department_id' => $request->validated('department_id'),
            'password' => $request->string('password')->toString(),
            'role' => UserRole::Staff,
            'is_active' => $request->boolean('is_active'),
            'must_change_password' => $request->boolean('must_change_password', true),
        ]);

        $employee->syncWorkSchedules($request->input('schedules', []));

        return redirect()->route('employees.index')->with('status', "{$employee->name} a été ajouté(e).");
    }

    public function edit(User $employee): Response
    {
        $employee->load('workSchedules');

        return Inertia::render('Employees/Form', [
            'employee' => (new EmployeeResource($employee))->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, User $employee): RedirectResponse
    {
        $employee->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->validated('email'),
            'phone' => $request->string('phone')->toString(),
            'department_id' => $request->validated('department_id'),
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->filled('password')) {
            $employee->password = $request->string('password')->toString();
            $employee->must_change_password = $request->boolean('must_change_password', true);
        }

        $employee->save();
        $employee->syncWorkSchedules($request->input('schedules', []));

        return redirect()->route('employees.index')->with('status', 'Modifications enregistrées.');
    }

    /**
     * @return array{departments: array<int, mixed>, weekdays: array<int, string>}
     */
    private function formOptions(): array
    {
        return [
            'departments' => DepartmentResource::collection(Department::query()->orderBy('name')->get())->resolve(),
            'weekdays' => WorkSchedule::weekdayLabels(),
        ];
    }
}
