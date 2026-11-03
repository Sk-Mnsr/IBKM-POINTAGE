<?php

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Http\Requests\ReviewLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $status = LeaveStatus::tryFrom($request->string('statut')->toString());

        $leaveRequests = LeaveRequest::query()
            ->with(['user', 'reviewer'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByRaw('case when status = ? then 0 else 1 end', [LeaveStatus::Pending->value])
            ->latest('starts_on')
            ->paginate(20)
            ->withQueryString();

        $countsByStatus = LeaveRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $today = today();

        return Inertia::render('Leaves/Index', [
            'leaveRequests' => $this->paginated($leaveRequests, LeaveRequestResource::class),
            'status' => $status?->value,
            'pendingCount' => (int) ($countsByStatus[LeaveStatus::Pending->value] ?? 0),
            'stats' => [
                'pending' => (int) ($countsByStatus[LeaveStatus::Pending->value] ?? 0),
                'approved' => (int) ($countsByStatus[LeaveStatus::Approved->value] ?? 0),
                'rejected' => (int) ($countsByStatus[LeaveStatus::Rejected->value] ?? 0),
                'onLeaveToday' => LeaveRequest::query()->approved()->overlapping($today, $today)->distinct()->count('user_id'),
            ],
            'upcoming' => LeaveRequestResource::collection(
                LeaveRequest::query()
                    ->with('user')
                    ->approved()
                    ->whereDate('ends_on', '>=', $today)
                    ->orderBy('starts_on')
                    ->limit(6)
                    ->get(),
            )->resolve(),
        ]);
    }

    public function update(ReviewLeaveRequestRequest $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        if (! $leaveRequest->isPending()) {
            return back()->withErrors(['leave' => 'Cette demande a déjà été traitée.']);
        }

        $status = LeaveStatus::from($request->string('status')->toString());

        $leaveRequest->update([
            'status' => $status,
            'review_note' => $request->validated('review_note'),
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', $status === LeaveStatus::Approved ? 'Demande acceptée.' : 'Demande refusée.');
    }
}
