<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceSummary;
use App\Support\DailyAttendance;
use App\Support\ReportMonth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffHistoryController extends Controller
{
    public function index(Request $request, AttendanceCalculator $calculator): Response
    {
        /** @var User $user */
        $user = $request->user();
        $month = ReportMonth::fromRequest($request);
        $days = $calculator->forUser($user, $month->start(), $month->end());

        return Inertia::render('Staff/History', [
            'month' => $month->toArray(),
            'days' => $days
                ->reject(fn (DailyAttendance $day): bool => $day->date->isAfter(today()))
                ->reverse()
                ->map(fn (DailyAttendance $day): array => $day->toArray())
                ->values()
                ->all(),
            'summary' => AttendanceSummary::fromDays($days)->toArray(),
        ]);
    }
}
