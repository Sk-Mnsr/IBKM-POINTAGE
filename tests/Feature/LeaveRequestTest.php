<?php

namespace Tests\Feature;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaveRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_request_leave(): void
    {
        $staff = User::factory()->create();

        $this->actingAs($staff)->post(route('staff.leaves.store'), [
            'type' => 'paid_leave',
            'starts_on' => today()->addDays(2)->toDateString(),
            'ends_on' => today()->addDays(4)->toDateString(),
            'reason' => 'Voyage',
        ])->assertRedirect(route('staff.leaves.index'))
            ->assertSessionHas('status', 'Votre demande a été envoyée au gérant.');

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $staff->id,
            'status' => LeaveStatus::Pending->value,
            'reason' => 'Voyage',
        ]);
    }

    public function test_leave_end_must_not_precede_start(): void
    {
        $this->actingAs(User::factory()->create())->post(route('staff.leaves.store'), [
            'type' => 'sick',
            'starts_on' => today()->addDays(4)->toDateString(),
            'ends_on' => today()->addDays(2)->toDateString(),
        ])->assertSessionHasErrors([
            'ends_on' => 'La date de fin doit être après la date de début.',
        ]);
    }

    public function test_admin_approves_a_pending_request(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $leave = LeaveRequest::factory()->create();

        $this->actingAs($admin)
            ->from(route('leaves.index'))
            ->patch(route('leaves.update', $leave), ['status' => 'approved', 'review_note' => 'Bon repos'])
            ->assertRedirect(route('leaves.index'))
            ->assertSessionHas('status', 'Demande acceptée.');

        $leave->refresh();
        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertSame($admin->id, $leave->reviewed_by);
        $this->assertSame('Bon repos', $leave->review_note);
    }

    public function test_review_rejects_a_pending_status(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch(route('leaves.update', LeaveRequest::factory()->create()), ['status' => 'pending'])
            ->assertSessionHasErrors(['status' => 'Décision invalide.']);
    }

    public function test_processed_request_cannot_be_reviewed_again(): void
    {
        $leave = LeaveRequest::factory()->approved()->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->patch(route('leaves.update', $leave), ['status' => 'rejected'])
            ->assertSessionHasErrors(['leave' => 'Cette demande a déjà été traitée.']);

        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);
    }

    public function test_review_page_counts_requests_and_lists_upcoming_absences(): void
    {
        LeaveRequest::factory()->create();
        LeaveRequest::factory()->approved()->create([
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(),
        ]);
        LeaveRequest::factory()->approved()->create([
            'starts_on' => today()->subDays(10)->toDateString(),
            'ends_on' => today()->subDays(8)->toDateString(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get(route('leaves.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Leaves/Index')
                ->where('stats.pending', 1)
                ->where('stats.approved', 2)
                ->where('stats.onLeaveToday', 1)
                ->has('upcoming', 1)
                ->where('upcoming.0.timing', 'En cours')
                ->where('leaveRequests.data.0.status.value', 'pending'));
    }

    public function test_staff_cannot_review_requests(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('leaves.index'))
            ->assertRedirect(route('punch.create'));
    }

    public function test_staff_cannot_cancel_a_colleague_request(): void
    {
        $leave = LeaveRequest::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('staff.leaves.destroy', $leave))
            ->assertNotFound();

        $this->assertModelExists($leave);
    }

    public function test_staff_can_cancel_only_pending_requests(): void
    {
        $staff = User::factory()->create();
        $pending = LeaveRequest::factory()->create(['user_id' => $staff->id]);
        $approved = LeaveRequest::factory()->approved()->create(['user_id' => $staff->id]);

        $this->actingAs($staff)->delete(route('staff.leaves.destroy', $pending))->assertRedirect(route('staff.leaves.index'));
        $this->assertModelMissing($pending);

        $this->actingAs($staff)
            ->delete(route('staff.leaves.destroy', $approved))
            ->assertSessionHasErrors(['leave' => 'Seule une demande en attente peut être annulée.']);
        $this->assertModelExists($approved);
    }
}
