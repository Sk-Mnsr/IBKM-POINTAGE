<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeaveRequestResource;
use App\Models\User;
use App\Support\AttendanceCalculator;
use App\Support\DailyAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffScheduleController extends Controller
{
    public function index(Request $request, AttendanceCalculator $calculator): Response
    {
        /** @var User $user */
        $user = $request->user();
        $weekStart = CarbonImmutable::today()->startOfWeek();

        return Inertia::render('Staff/Schedule', [
            'weeks' => $calculator->forUser($user, $weekStart, $weekStart->addDays(13))
                ->map(fn (DailyAttendance $day): array => $day->toArray())
                ->values()
                ->chunk(7)
                ->map(fn ($week): array => $week->values()->all())
                ->all(),
            'upcomingLeaves' => LeaveRequestResource::collection(
                $user->leaveRequests()
                    ->approved()
                    ->whereDate('ends_on', '>=', today())
                    ->orderBy('starts_on')
                    ->get(),
            )->resolve(),
        ]);
    }
}
