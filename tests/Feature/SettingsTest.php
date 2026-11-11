<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_changes_the_institute_name_and_late_tolerance(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->put(route('settings.update'), ['institute_name' => 'King Maelick Group', 'late_tolerance_minutes' => 10])
            ->assertSessionHasNoErrors();

        $this->assertSame('10', Setting::query()->find('late_tolerance_minutes')?->value);

        $this->actingAs($superAdmin)
            ->get(route('settings.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.instituteName', 'King Maelick Group')
                ->where('settings.lateToleranceMinutes', 10)
                ->where('app.instituteName', 'King Maelick Group'));
    }

    public function test_new_tolerance_changes_what_counts_as_late(): void
    {
        $schedule = WorkSchedule::factory()->create(['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        $arrival = CarbonImmutable::parse('2026-11-09 09:08');

        $this->assertSame(8, $schedule->lateMinutesFor($arrival));

        Setting::store(['late_tolerance_minutes' => 10]);

        $this->assertSame(0, $schedule->lateMinutesFor($arrival));
    }

    public function test_tolerance_must_stay_reasonable(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('settings.update'), ['institute_name' => 'King Maelick', 'late_tolerance_minutes' => 120])
            ->assertSessionHasErrors('late_tolerance_minutes');
    }

    public function test_shop_manager_cannot_open_settings(): void
    {
        $manager = User::factory()->admin()->create();

        $this->actingAs($manager)->get(route('settings.edit'))->assertRedirect(route('dashboard'));
        $this->actingAs($manager)
            ->put(route('settings.update'), ['institute_name' => 'Pirate', 'late_tolerance_minutes' => 0])
            ->assertForbidden();
    }
}
