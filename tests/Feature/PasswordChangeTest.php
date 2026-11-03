<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_employee_must_choose_a_password_before_using_the_app(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('employees.store'), [
            'name' => 'Awa Ndiaye',
            'phone' => '771234567',
            'password' => 'provisoire1',
            'is_active' => true,
        ]);

        $employee = User::query()->where('phone', '771234567')->sole();
        $this->assertTrue($employee->must_change_password);

        $this->actingAs($employee)
            ->get(route('punch.create'))
            ->assertRedirect(route('password.edit'));

        $this->actingAs($employee)
            ->get(route('password.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ChangePassword')
                ->where('forced', true));
    }

    public function test_forced_change_does_not_ask_for_the_current_password(): void
    {
        $employee = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($employee)
            ->put(route('password.update'), [
                'password' => 'monSecret2026',
                'password_confirmation' => 'monSecret2026',
            ])
            ->assertRedirect(route('punch.create'))
            ->assertSessionHas('status', 'Votre mot de passe a été mis à jour.');

        $employee->refresh();
        $this->assertFalse($employee->must_change_password);
        $this->assertNotNull($employee->password_changed_at);
        $this->assertTrue(Hash::check('monSecret2026', $employee->password));

        $this->actingAs($employee)->get(route('punch.create'))->assertOk();
    }

    public function test_voluntary_change_requires_the_current_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('password.update'), [
                'current_password' => 'mauvais',
                'password' => 'nouveauPass9',
                'password_confirmation' => 'nouveauPass9',
            ])
            ->assertSessionHasErrors(['current_password' => 'Le mot de passe actuel est incorrect.']);

        $this->actingAs($admin)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'nouveauPass9',
                'password_confirmation' => 'nouveauPass9',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Hash::check('nouveauPass9', $admin->fresh()->password));
    }

    public function test_new_password_must_differ_and_be_confirmed(): void
    {
        $employee = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($employee)
            ->put(route('password.update'), [
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors(['password' => 'Choisissez un mot de passe différent de l\'actuel.']);

        $this->actingAs($employee)
            ->put(route('password.update'), [
                'password' => 'nouveauPass9',
                'password_confirmation' => 'autreChose9',
            ])
            ->assertSessionHasErrors(['password' => 'Les deux mots de passe ne correspondent pas.']);
    }

    public function test_manager_reset_requires_a_new_change_unless_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($admin)->put(route('employees.update', $employee), [
            'name' => $employee->name,
            'phone' => $employee->phone,
            'password' => 'reinitialise1',
            'is_active' => true,
        ]);
        $this->assertTrue($employee->fresh()->must_change_password);

        $other = User::factory()->create();
        $this->actingAs($admin)->put(route('employees.update', $other), [
            'name' => $other->name,
            'phone' => $other->phone,
            'password' => 'reinitialise1',
            'must_change_password' => false,
            'is_active' => true,
        ]);
        $this->assertFalse($other->fresh()->must_change_password);
    }

    public function test_editing_without_new_password_keeps_the_flag_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($admin)->put(route('employees.update', $employee), [
            'name' => 'Nouveau Nom',
            'phone' => $employee->phone,
            'password' => '',
            'is_active' => true,
        ]);

        $this->assertFalse($employee->fresh()->must_change_password);
    }

    public function test_offline_sync_is_refused_until_the_password_is_changed(): void
    {
        $employee = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($employee)
            ->postJson(route('punch.sync'), [])
            ->assertForbidden();
    }
}
