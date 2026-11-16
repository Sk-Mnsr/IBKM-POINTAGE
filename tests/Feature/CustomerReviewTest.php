<?php

namespace Tests\Feature;

use App\Enums\CustomerReviewRating;
use App\Models\CustomerReview;
use App\Models\Kiosk;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_the_public_review_form_for_an_open_kiosk(): void
    {
        $kiosk = Kiosk::factory()->create(['name' => 'Accueil']);

        $this->get(route('kiosk.reviews.show', $kiosk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kiosk/Reviews')
                ->where('kioskName', 'Accueil')
                ->where('question', 'Comment s’est passée votre visite ?')
                ->has('ratings', 3)
                ->where('submitUrl', route('kiosk.reviews.store', $kiosk)));
    }

    public function test_guest_cannot_open_reviews_for_a_closed_kiosk(): void
    {
        $kiosk = Kiosk::factory()->create(['is_active' => false]);

        $this->get(route('kiosk.reviews.show', $kiosk))->assertNotFound();
        $this->postJson(route('kiosk.reviews.store', $kiosk), [
            'rating' => CustomerReviewRating::Satisfied->value,
        ])->assertNotFound();
    }

    public function test_guest_can_submit_a_rating_with_an_optional_note(): void
    {
        $kiosk = Kiosk::factory()->create();

        $this->postJson(route('kiosk.reviews.store', $kiosk), [
            'rating' => CustomerReviewRating::VerySatisfied->value,
            'comment' => 'Accueil très chaleureux',
        ])
            ->assertCreated()
            ->assertJson(['message' => 'Merci pour votre avis.']);

        $this->assertDatabaseHas('customer_reviews', [
            'shop_id' => $kiosk->shop_id,
            'kiosk_id' => $kiosk->id,
            'rating' => CustomerReviewRating::VerySatisfied->value,
            'comment' => 'Accueil très chaleureux',
        ]);
    }

    public function test_blank_comment_is_stored_as_null(): void
    {
        $kiosk = Kiosk::factory()->create();

        $this->postJson(route('kiosk.reviews.store', $kiosk), [
            'rating' => CustomerReviewRating::Unsatisfied->value,
            'comment' => '   ',
        ])->assertCreated();

        $this->assertDatabaseHas('customer_reviews', [
            'kiosk_id' => $kiosk->id,
            'rating' => CustomerReviewRating::Unsatisfied->value,
            'comment' => null,
        ]);
    }

    public function test_rating_is_required(): void
    {
        $kiosk = Kiosk::factory()->create();

        $this->postJson(route('kiosk.reviews.store', $kiosk), [
            'comment' => 'Sans note',
        ])->assertUnprocessable()->assertJsonValidationErrors(['rating']);

        $this->assertDatabaseCount('customer_reviews', 0);
    }

    public function test_kiosk_screen_exposes_the_review_and_scan_urls(): void
    {
        $kiosk = Kiosk::factory()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('kiosk.show', $kiosk))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kiosk/Show')
                ->where('reviewUrl', route('kiosk.reviews.show', $kiosk))
                ->where('scanUrl', route('kiosk.scan', $kiosk)));
    }

    public function test_scanning_the_punch_qr_offers_customer_review_to_guests(): void
    {
        $kiosk = Kiosk::factory()->create(['name' => 'Accueil']);
        $token = 'demo-token';

        $this->get(route('kiosk.scan', ['kiosk' => $kiosk, 'code' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kiosk/Scan')
                ->where('kioskName', 'Accueil')
                ->where('punchUrl', route('punch.create', ['code' => $token]))
                ->where('reviewUrl', route('kiosk.reviews.show', $kiosk)));
    }

    public function test_staff_scanning_the_qr_goes_straight_to_punch(): void
    {
        $kiosk = Kiosk::factory()->create();
        $token = 'staff-token';

        $this->actingAs(User::factory()->create())
            ->get(route('kiosk.scan', ['kiosk' => $kiosk, 'code' => $token]))
            ->assertRedirect(route('punch.create', ['code' => $token]));
    }

    public function test_manager_sees_only_reviews_of_their_shops(): void
    {
        $plateau = Shop::factory()->create(['name' => 'Plateau']);
        $almadies = Shop::factory()->create(['name' => 'Almadies']);
        $manager = User::factory()->forShops($plateau)->create();

        $plateauKiosk = Kiosk::factory()->create(['shop_id' => $plateau->id, 'name' => 'Borne Plateau']);
        $almadiesKiosk = Kiosk::factory()->create(['shop_id' => $almadies->id, 'name' => 'Borne Almadies']);

        CustomerReview::factory()->verySatisfied()->create([
            'shop_id' => $plateau->id,
            'kiosk_id' => $plateauKiosk->id,
            'comment' => 'Super Plateau',
        ]);
        CustomerReview::factory()->unsatisfied()->create([
            'shop_id' => $almadies->id,
            'kiosk_id' => $almadiesKiosk->id,
            'comment' => 'Secret Almadies',
        ]);

        $this->actingAs($manager)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reviews/Index')
                ->where('stats.total', 1)
                ->where('stats.verySatisfied', 1)
                ->where('reviews.total', 1)
                ->where('reviews.data.0.comment', 'Super Plateau')
                ->where('reviews.data.0.kiosk', 'Borne Plateau')
                ->has('byKiosk', 1));
    }

    public function test_staff_cannot_open_the_reviews_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reviews.index'))
            ->assertRedirect(route('punch.create'));
    }

    public function test_guest_cannot_open_the_reviews_dashboard(): void
    {
        $this->get(route('reviews.index'))->assertRedirect(route('login'));
    }

    public function test_reviews_can_be_filtered_by_kiosk(): void
    {
        $shop = Shop::factory()->create();
        $first = Kiosk::factory()->create(['shop_id' => $shop->id, 'name' => 'Accueil']);
        $second = Kiosk::factory()->create(['shop_id' => $shop->id, 'name' => 'Caisse']);
        $admin = User::factory()->superAdmin()->create();

        CustomerReview::factory()->create([
            'shop_id' => $shop->id,
            'kiosk_id' => $first->id,
            'comment' => 'Avis accueil',
        ]);
        CustomerReview::factory()->create([
            'shop_id' => $shop->id,
            'kiosk_id' => $second->id,
            'comment' => 'Avis caisse',
        ]);

        $this->actingAs($admin)
            ->get(route('reviews.index', ['borne' => $first->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('reviews.total', 1)
                ->where('reviews.data.0.comment', 'Avis accueil')
                ->where('filters.borne', $first->id));
    }

    public function test_admin_sees_todays_unsatisfied_review_count(): void
    {
        $shop = Shop::factory()->create();
        $kiosk = Kiosk::factory()->create(['shop_id' => $shop->id]);
        $admin = User::factory()->superAdmin()->create();

        CustomerReview::factory()->unsatisfied()->create([
            'shop_id' => $shop->id,
            'kiosk_id' => $kiosk->id,
        ]);
        CustomerReview::factory()->verySatisfied()->create([
            'shop_id' => $shop->id,
            'kiosk_id' => $kiosk->id,
        ]);
        CustomerReview::factory()->unsatisfied()->create([
            'shop_id' => $shop->id,
            'kiosk_id' => $kiosk->id,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('unsatisfiedReviewCount', 1));
    }
}
