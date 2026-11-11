<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $holiday = Holiday::query()->create($request->validated());

        return back()->with('status', "{$holiday->name} ajouté au calendrier.");
    }

    /**
     * Adds the holidays whose date is known in advance (fixed dates and Easter-based) for a year.
     */
    public function storeSuggestions(Request $request): RedirectResponse
    {
        $year = (int) $request->validate(['year' => ['required', 'integer', 'min:2020', 'max:2100']])['year'];
        $added = 0;

        foreach (Holiday::suggestionsFor($year) as $date => $name) {
            if (! Holiday::query()->whereDate('date', $date)->exists()) {
                Holiday::query()->create(['date' => $date, 'name' => $name]);
                $added++;
            }
        }

        return back()->with('status', $added > 0 ? "{$added} jour(s) férié(s) ajouté(s) pour {$year}." : "Les fêtes à date fixe de {$year} sont déjà enregistrées.");
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('status', "{$holiday->name} retiré du calendrier.");
    }
}
