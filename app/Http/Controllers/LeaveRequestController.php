<?php

namespace App\Http\Controllers;

use App\Enums\LeaveType;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveRequestController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Staff/Leaves', [
            'leaveRequests' => LeaveRequestResource::collection($user->leaveRequests()->latest('starts_on')->get())->resolve(),
            'types' => array_map(
                fn (LeaveType $type): array => ['value' => $type->value, 'label' => $type->label()],
                LeaveType::cases(),
            ),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StoreLeaveRequestRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->leaveRequests()->create([
            'type' => $request->validated('type'),
            'starts_on' => $request->validated('starts_on'),
            'ends_on' => $request->validated('ends_on'),
            'reason' => $request->validated('reason'),
        ]);

        return redirect()
            ->route('staff.leaves.index')
            ->with('status', 'Votre demande a été envoyée au gérant.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()?->id, 404);

        if (! $leaveRequest->isPending()) {
            return redirect()
                ->route('staff.leaves.index')
                ->withErrors(['leave' => 'Seule une demande en attente peut être annulée.']);
        }

        $leaveRequest->delete();

        return redirect()
            ->route('staff.leaves.index')
            ->with('status', 'Demande annulée.');
    }
}
