<?php

namespace App\Http\Controllers;

use App\Actions\CorrectPunches;
use App\Enums\PunchType;
use App\Http\Requests\DeletePunchCorrectionRequest;
use App\Http\Requests\StorePunchCorrectionRequest;
use App\Http\Requests\UpdatePunchCorrectionRequest;
use App\Models\Punch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class PunchCorrectionController extends Controller
{
    public function __construct(private CorrectPunches $corrections) {}

    public function store(StorePunchCorrectionRequest $request, User $employee): RedirectResponse
    {
        $at = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $request->string('date').' '.$request->string('time'),
            config('app.timezone'),
        );

        $punch = $this->corrections->add(
            $employee,
            $request->user(),
            $at,
            PunchType::from($request->string('type')->toString()),
            $request->string('reason')->toString(),
        );

        return back()->with('status', "{$punch->type->label()} ajouté(e) à {$punch->punched_at->format('H:i')}.");
    }

    public function update(UpdatePunchCorrectionRequest $request, Punch $punch): RedirectResponse
    {
        $this->ensureStaffPunch($punch);

        $this->corrections->update(
            $punch,
            $request->user(),
            $request->string('time')->toString(),
            PunchType::from($request->string('type')->toString()),
            $request->string('reason')->toString(),
        );

        return back()->with('status', 'Pointage modifié.');
    }

    public function destroy(DeletePunchCorrectionRequest $request, Punch $punch): RedirectResponse
    {
        $this->ensureStaffPunch($punch);

        $this->corrections->delete($punch, $request->user(), $request->string('reason')->toString());

        return back()->with('status', 'Pointage supprimé. Il reste visible dans le journal des corrections.');
    }

    private function ensureStaffPunch(Punch $punch): void
    {
        abort_if($punch->user === null || $punch->user->isAdmin(), 404);
    }
}
