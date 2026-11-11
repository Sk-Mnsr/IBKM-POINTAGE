<?php

namespace Tests\Feature;

use App\Models\Kiosk;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopKioskManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->shop = Shop::factory()->create();
    }

    public function test_super_admin_adds_a_second_kiosk_to_a_shop(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('shops.kiosks.store', $this->shop), ['name' => 'Entrée arrière'])
            ->assertSessionHasNoErrors();

        $kiosk = $this->shop->kiosks()->sole();

        $this->assertSame('Entrée arrière', $kiosk->name);
        $this->assertTrue($kiosk->is_active);
        $this->assertNotEmpty($kiosk->public_id);
    }

    public function test_kiosk_names_are_unique_within_a_shop(): void
    {
        Kiosk::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Accueil']);

        $this->actingAs($this->superAdmin)
            ->post(route('shops.kiosks.store', $this->shop), ['name' => 'Accueil'])
            ->assertSessionHasErrors('name');
    }

    public function test_deactivated_kiosk_stops_showing_codes(): void
    {
        $kiosk = Kiosk::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Accueil']);

        $this->actingAs($this->superAdmin)
            ->put(route('shops.kiosks.update', [$this->shop, $kiosk]), ['name' => 'Accueil principal', 'is_active' => false])
            ->assertSessionHasNoErrors();

        $this->assertSame('Accueil principal', $kiosk->fresh()->name);
        $this->actingAs($this->superAdmin)->get(route('kiosk.show', $kiosk))->assertNotFound();
    }

    public function test_kiosk_must_belong_to_the_shop_in_the_address(): void
    {
        $otherKiosk = Kiosk::factory()->create(['shop_id' => Shop::factory()->create()->id]);

        $this->actingAs($this->superAdmin)
            ->put(route('shops.kiosks.update', [$this->shop, $otherKiosk]), ['name' => 'Piratée'])
            ->assertNotFound();
    }

    public function test_shop_manager_cannot_add_kiosks(): void
    {
        $this->actingAs(User::factory()->forShops($this->shop)->create())
            ->post(route('shops.kiosks.store', $this->shop), ['name' => 'Entrée arrière'])
            ->assertForbidden();
    }
}
