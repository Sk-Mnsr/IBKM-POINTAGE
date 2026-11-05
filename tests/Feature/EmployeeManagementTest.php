<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_sign_in_and_open_the_dashboard(): void
    {
        User::factory()->superAdmin()->create([
            'email' => 'gerant@ibkn.sn',
        ]);

        $this->post(route('login.store'), [
            'identifier' => 'gerant@ibkn.sn',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.role', 'super_admin'));
    }

    public function test_employee_list_includes_schedules_and_departments_for_filtering(): void
    {
        $department = Department::factory()->create(['name' => 'Coiffure']);
        $employee = User::factory()->create(['department_id' => $department->id]);
        $employee->syncWorkSchedules([1 => ['starts_at' => '09:00', 'ends_at' => '18:00']]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Index')
                ->has('employees', 1)
                ->where('employees.0.schedules.0.starts_at', '09:00')
                ->where('departments.0.name', 'Coiffure'));
    }

    public function test_staff_opening_the_employee_list_is_sent_back_to_their_space(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('employees.index'))
            ->assertRedirect(route('punch.create'));

        $this->actingAs(User::factory()->create())
            ->getJson(route('employees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_an_employee_with_a_schedule(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $department = Department::factory()->create();

        $this->actingAs($admin)->post(route('employees.store'), [
            'name' => 'Awa Ndiaye',
            'phone' => '771234567',
            'email' => '',
            'department_id' => $department->id,
            'password' => 'secretpass',
            'is_active' => '1',
            'schedules' => [
                1 => ['starts_at' => '09:00', 'ends_at' => '18:00'],
            ],
        ])->assertRedirect(route('employees.index'));

        $employee = User::query()->where('phone', '771234567')->first();

        $this->assertNotNull($employee);
        $this->assertSame('staff', $employee->role->value);
        $this->assertNull($employee->email);
        $this->assertSame('09:00', $employee->workSchedules()->first()?->formattedStart());
        $this->assertSame('18:00', $employee->workSchedules()->first()?->formattedEnd());
    }

    public function test_edit_form_keeps_each_schedule_on_its_own_weekday(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $employee = User::factory()->create();
        $employee->syncWorkSchedules([
            1 => ['starts_at' => '09:00', 'ends_at' => '18:00'],
            6 => ['starts_at' => '10:00', 'ends_at' => '14:00'],
        ]);

        $this->actingAs($admin)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employees/Form')
                ->has('employee.schedules', 2)
                ->where('employee.schedules.0.weekday', 1)
                ->where('employee.schedules.1.weekday', 6)
                ->where('employee.schedules.1.starts_at', '10:00'));
    }

    public function test_employee_form_requires_a_name(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('employees.store'), [
            'name' => '',
            'phone' => '',
            'password' => '',
            'is_active' => '1',
        ])->assertSessionHasErrors([
            'name' => 'Le nom est obligatoire.',
        ]);
    }

    public function test_schedule_end_must_be_after_the_start(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->post(route('employees.store'), [
            'name' => 'Awa Ndiaye',
            'phone' => '771234567',
            'password' => 'secretpass',
            'is_active' => '1',
            'schedules' => [
                1 => ['starts_at' => '18:00', 'ends_at' => '09:00'],
            ],
        ])->assertSessionHasErrors([
            'schedules.1.ends_at' => 'L\'heure de fin doit être après l\'heure de début.',
        ]);
    }

    public function test_department_with_employees_cannot_be_deleted(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $department = Department::factory()->create();
        User::factory()->create(['department_id' => $department->id]);

        $this->actingAs($admin)
            ->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'))
            ->assertSessionHasErrors([
                'name' => 'Ce département a encore des employés.',
            ]);

        $this->assertModelExists($department);
    }
}
