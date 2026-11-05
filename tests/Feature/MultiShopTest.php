<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Enums\PunchType;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Kiosk;
use App\Models\LeaveRequest;
use App\Models\Punch;
use App\Models\Shop;
use App\Models\User;
use App\Support\KioskToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiShopTest extends TestCase
{
    use RefreshDatabase;

    private Shop $plateau;

    private Shop $almadies;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plateau = Shop::factory()->create(['name' => 'Plateau']);
        $this->almadies = Shop::factory()->create(['name' => 'Almadies']);
    }

    public function test_shop_manager_only_sees_the_staff_of_their_shops(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();
        User::factory()->create(['name' => 'Awa Plateau', 'shop_id' => $this->plateau->id]);
        User::factory()->create(['name' => 'Fatou Almadies', 'shop_id' => $this->almadies->id]);

        $this->actingAs($manager)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 1)
                ->where('employees.0.name', 'Awa Plateau')
                ->where('shopContext.current.name', 'Plateau')
                ->has('shopContext.shops', 1));

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 1)
                ->where('rows.0.employee.name', 'Awa Plateau'));
    }

    public function test_shop_manager_cannot_open_records_of_another_shop(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();
        $outsider = User::factory()->create(['shop_id' => $this->almadies->id]);
        $punch = Punch::factory()->create(['user_id' => $outsider->id]);
        $leave = LeaveRequest::factory()->create(['user_id' => $outsider->id]);
        $department = Department::factory()->create(['shop_id' => $this->almadies->id]);

        $this->actingAs($manager)->get(route('employees.edit', $outsider))->assertNotFound();
        $this->actingAs($manager)->get(route('reports.employee', $outsider))->assertNotFound();
        $this->actingAs($manager)->get(route('departments.edit', $department))->assertNotFound();
        $this->actingAs($manager)->delete(route('punches.corrections.destroy', $punch), ['reason' => 'Erreur'])->assertNotFound();
        $this->actingAs($manager)
            ->patch(route('leaves.update', $leave), ['status' => LeaveStatus::Approved->value])
            ->assertNotFound();

        $this->assertSame(LeaveStatus::Pending, $leave->fresh()->status);
        $this->assertNotSoftDeleted($punch);
    }

    public function test_punch_history_is_limited_to_the_managed_shops(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();
        Punch::factory()->create(['user_id' => User::factory()->create(['shop_id' => $this->plateau->id])->id]);
        Punch::factory()->create(['user_id' => User::factory()->create(['shop_id' => $this->almadies->id])->id]);

        $this->actingAs($manager)
            ->get(route('punches.index'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 1));
    }

    public function test_shop_manager_cannot_create_an_employee_in_another_shop(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();

        $this->actingAs($manager)->post(route('employees.store'), [
            'name' => 'Intruse',
            'phone' => '771112233',
            'password' => 'secretpass',
            'is_active' => true,
            'shop_id' => $this->almadies->id,
        ])->assertSessionHasErrors('shop_id');

        $this->assertDatabaseMissing('users', ['phone' => '771112233']);
    }

    public function test_employee_department_must_belong_to_the_chosen_shop(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();
        $otherDepartment = Department::factory()->create(['shop_id' => $this->almadies->id]);

        $this->actingAs($manager)->post(route('employees.store'), [
            'name' => 'Awa',
            'phone' => '771112233',
            'password' => 'secretpass',
            'is_active' => true,
            'shop_id' => $this->plateau->id,
            'department_id' => $otherDepartment->id,
        ])->assertSessionHasErrors('department_id');
    }

    public function test_manager_of_several_shops_can_switch_between_them(): void
    {
        $manager = User::factory()->forShops($this->plateau, $this->almadies)->create();
        User::factory()->create(['shop_id' => $this->plateau->id]);
        User::factory()->create(['shop_id' => $this->almadies->id]);

        $this->actingAs($manager)
            ->get(route('employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 2)
                ->where('shopContext.current', null)
                ->where('showShop', true));

        $this->actingAs($manager)
            ->put(route('current-shop.update'), ['shop_id' => $this->almadies->id])
            ->assertRedirect();

        $this->actingAs($manager)
            ->get(route('employees.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('employees', 1)
                ->where('shopContext.current.name', 'Almadies'));
    }

    public function test_manager_cannot_switch_to_a_shop_they_do_not_manage(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();

        $this->actingAs($manager)
            ->put(route('current-shop.update'), ['shop_id' => $this->almadies->id])
            ->assertForbidden();
    }

    public function test_staff_cannot_punch_on_the_kiosk_of_another_shop(): void
    {
        $staff = User::factory()->create(['shop_id' => $this->plateau->id]);
        $kiosk = Kiosk::factory()->create(['shop_id' => $this->almadies->id]);

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => app(KioskToken::class)->issue($kiosk)['token'],
            'type' => PunchType::Arrival->value,
        ])->assertSessionHasErrors(['token' => 'Ce code appartient à une autre boutique. Scannez la borne de votre boutique.']);

        $this->assertDatabaseCount('punches', 0);
    }

    public function test_closed_shop_kiosk_no_longer_accepts_punches(): void
    {
        $this->plateau->update(['is_active' => false]);
        $staff = User::factory()->create(['shop_id' => $this->plateau->id]);
        $kiosk = Kiosk::factory()->create(['shop_id' => $this->plateau->id]);

        $this->actingAs($staff)->post(route('punch.store'), [
            'token' => app(KioskToken::class)->issue($kiosk)['token'],
            'type' => PunchType::Arrival->value,
        ])->assertSessionHasErrors('token');
    }

    public function test_shop_manager_can_only_pair_kiosks_of_their_shops(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();
        $kiosk = Kiosk::factory()->create(['shop_id' => $this->almadies->id]);

        $this->actingAs($manager)->post(route('kiosk.pair', $kiosk))->assertNotFound();
        $this->actingAs($manager)
            ->get(route('kiosk.show', $kiosk))
            ->assertInertia(fn (Assert $page) => $page->component('Kiosk/Unpaired'));
    }

    public function test_super_admin_creates_a_shop_with_its_departments_and_kiosk(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('shops.store'), [
            'name' => 'King Maelick Mermoz',
            'address' => 'Mermoz, Dakar',
            'phone' => '33 800 00 00',
            'is_active' => true,
        ])->assertRedirect(route('shops.index'));

        $shop = Shop::query()->where('name', 'King Maelick Mermoz')->sole();

        $this->assertSame('338000000', $shop->phone);
        $this->assertSame(Shop::DEFAULT_DEPARTMENTS, $shop->departments()->orderBy('id')->pluck('name')->all());
        $this->assertSame(1, $shop->kiosks()->count());
    }

    public function test_shop_edit_page_shows_its_team_and_kiosks(): void
    {
        $this->plateau->provisionDefaults();
        $manager = User::factory()->forShops($this->plateau)->create(['name' => 'Ndeye Gérante']);
        $coiffure = $this->plateau->departments()->where('name', 'Coiffure')->sole();
        User::factory()->create(['shop_id' => $this->plateau->id, 'department_id' => $coiffure->id]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('shops.edit', $this->plateau))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shops/Form')
                ->where('shop.staffCount', 1)
                ->where('shop.managers.0.id', $manager->id)
                ->has('shop.kiosks', 1)
                ->where('shop.departments', fn ($departments) => collect($departments)->firstWhere('name', 'Coiffure')['staffCount'] === 1));
    }

    public function test_super_admin_creates_a_manager_for_given_shops(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('managers.store'), [
            'name' => 'Ndeye Gérante',
            'phone' => '770001122',
            'email' => '',
            'password' => 'provisoire1',
            'role' => UserRole::Admin->value,
            'shop_ids' => [$this->almadies->id],
            'is_active' => true,
        ])->assertRedirect(route('managers.index'));

        $manager = User::query()->where('phone', '770001122')->sole();

        $this->assertSame(UserRole::Admin, $manager->role);
        $this->assertTrue($manager->must_change_password);
        $this->assertSame([$this->almadies->id], $manager->accessibleShopIds());
    }

    public function test_shop_manager_needs_at_least_one_shop(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->post(route('managers.store'), [
            'name' => 'Sans boutique',
            'phone' => '770001122',
            'password' => 'provisoire1',
            'role' => UserRole::Admin->value,
            'shop_ids' => [],
            'is_active' => true,
        ])->assertSessionHasErrors('shop_ids');
    }

    public function test_super_admin_cannot_demote_themselves(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->put(route('managers.update', $superAdmin), [
            'name' => $superAdmin->name,
            'phone' => $superAdmin->phone,
            'role' => UserRole::Admin->value,
            'shop_ids' => [$this->plateau->id],
            'is_active' => true,
        ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::SuperAdmin, $superAdmin->fresh()->role);
    }

    public function test_shop_manager_cannot_manage_shops_or_managers(): void
    {
        $manager = User::factory()->forShops($this->plateau)->create();

        $this->actingAs($manager)->get(route('shops.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($manager)->get(route('managers.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($manager)->post(route('shops.store'), ['name' => 'Pirate', 'is_active' => true])->assertForbidden();
    }

    public function test_super_admin_sees_every_shop(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        User::factory()->create(['shop_id' => $this->plateau->id]);
        User::factory()->create(['shop_id' => $this->almadies->id]);

        $this->actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows', 2)
                ->has('shopContext.shops', 2)
                ->where('breakdownTitle', 'Par boutique'));
    }
}
