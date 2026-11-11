<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Holiday;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $year = $request->integer('annee') ?: (int) today()->year;
        $year = max(2020, min(2100, $year));

        $holidays = Holiday::query()
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        $saved = $holidays->map(fn (Holiday $holiday): string => $holiday->date->toDateString())->all();

        return Inertia::render('Settings/Edit', [
            'settings' => [
                'instituteName' => (string) config('pointage.institute_name'),
                'lateToleranceMinutes' => (int) config('pointage.late_tolerance_minutes'),
            ],
            'year' => $year,
            'holidays' => $holidays->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'date' => $holiday->date->toDateString(),
                'dayNumber' => $holiday->date->format('d'),
                'month' => $holiday->date->translatedFormat('M'),
                'weekday' => ucfirst($holiday->date->translatedFormat('l')),
                'name' => $holiday->name,
                'isPast' => $holiday->date->isBefore(CarbonImmutable::today()),
            ])->all(),
            'missingSuggestions' => collect(Holiday::suggestionsFor($year))
                ->except($saved)
                ->map(fn (string $name, string $date): array => [
                    'date' => $date,
                    'label' => CarbonImmutable::parse($date)->translatedFormat('j M'),
                    'name' => $name,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        Setting::store($request->validated());

        return back()->with('status', 'Réglages enregistrés.');
    }
}
