<?php

namespace App\Http\Middleware;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'instituteName' => config('pointage.institute_name'),
                'lateToleranceMinutes' => (int) config('pointage.late_tolerance_minutes'),
            ],
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'initials' => $user->initials(),
                    'role' => $user->role->value,
                    'department' => $user->department?->name,
                ],
            ],
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
            'pendingLeaveCount' => fn (): int => $user?->isAdmin()
                ? LeaveRequest::query()->where('status', LeaveStatus::Pending)->count()
                : 0,
        ];
    }
}
