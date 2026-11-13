<?php

namespace App\Http\Controllers;

use App\Actions\RecordPunch;
use App\Enums\PunchType;
use App\Http\Requests\StorePunchRequest;
use App\Http\Requests\SyncPunchRequest;
use App\Models\Punch;
use App\Models\User;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PunchController extends Controller
{
    public function create(Request $request, AttendanceCalculator $calculator): Response
    {
        /** @var User $user */
        $user = $request->user();
        $today = CarbonImmutable::today();

        $day = AttendanceDay::for($user);

        return Inertia::render('Staff/Punch', [
            'todayLabel' => ucfirst($today->translatedFormat('l j F')),
            'actions' => array_map(
                fn (PunchType $type): array => $type->toArray(),
                $day->allowedTypes(),
            ),
            'closedReason' => $day->closedReason,
            'attendance' => $calculator->forUser($user, $today, $today)->first()->toArray(),
            'scannedToken' => $request->string('code')->limit(255, '')->toString(),
            'punchTypes' => array_map(fn (PunchType $type): array => $type->toArray(), PunchType::cases()),
            'givenName' => $user->givenName(),
            'syncUrl' => route('punch.sync'),
        ]);
    }

    public function store(StorePunchRequest $request, RecordPunch $recordPunch): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $type = PunchType::from($request->string('type')->toString());
        $punch = $recordPunch->handle($user, $request->string('token')->toString(), $type);

        return redirect()
            ->route('punch.create')
            ->with('status', $punch->type->confirmation($user->givenName()));
    }

    /**
     * Receives a punch the phone stored while it had no network. Sending the same punch twice is harmless.
     */
    public function sync(SyncPunchRequest $request, RecordPunch $recordPunch): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $uuid = $request->string('uuid')->toString();

        $existing = Punch::query()->where('uuid', $uuid)->first();

        if ($existing !== null) {
            abort_unless($existing->user_id === $user->id, 409);

            return response()->json(['status' => 'duplicate', 'time' => $existing->punched_at->format('H:i')]);
        }

        $punch = $recordPunch->handleOffline(
            $user,
            $uuid,
            $request->string('token')->toString(),
            PunchType::from($request->string('type')->toString()),
            CarbonImmutable::parse($request->string('scanned_at')->toString())->setTimezone(config('app.timezone')),
        );

        return response()->json([
            'status' => 'recorded',
            'message' => $punch->type->confirmation($user->givenName()),
            'label' => $punch->type->label(),
            'time' => $punch->punched_at->format('H:i'),
        ], 201);
    }
}
