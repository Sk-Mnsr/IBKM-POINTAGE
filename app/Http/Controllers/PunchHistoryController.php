<?php

namespace App\Http\Controllers;

use App\Enums\PunchType;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PunchResource;
use App\Models\Department;
use App\Models\Punch;
use App\Models\User;
use App\Support\SpreadsheetExporter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PunchHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $countsByType = $this->query($filters)
            ->reorder()
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return Inertia::render('Punches/Index', [
            'punches' => $this->paginated(
                $this->query($filters)
                    ->with(['user.department', 'user.workSchedules', 'kiosk'])
                    ->withCount('corrections')
                    ->paginate(30)
                    ->withQueryString(),
                PunchResource::class,
            ),
            'stats' => [
                'total' => (int) $countsByType->sum(),
                'arrivals' => (int) ($countsByType[PunchType::Arrival->value] ?? 0),
                'breaks' => (int) ($countsByType[PunchType::BreakStart->value] ?? 0),
                'departures' => (int) ($countsByType[PunchType::Departure->value] ?? 0),
                'employees' => $this->query($filters)->reorder()->distinct()->count('user_id'),
            ],
            'today' => CarbonImmutable::today()->toDateString(),
            'filters' => [
                'du' => $filters['from']->toDateString(),
                'au' => $filters['to']->toDateString(),
                'employe' => $filters['employee'],
                'departement' => $filters['department'],
                'action' => $filters['type']?->value,
            ],
            'periodLabel' => 'du '.$filters['from']->format('d/m/Y').' au '.$filters['to']->format('d/m/Y'),
            'employees' => User::query()->staff()->orderBy('name')->get(['id', 'name']),
            'departments' => DepartmentResource::collection(Department::query()->orderBy('name')->get())->resolve(),
            'types' => array_map(fn (PunchType $type): array => $type->toArray(), PunchType::cases()),
        ]);
    }

    public function export(Request $request, SpreadsheetExporter $exporter): BinaryFileResponse
    {
        $filters = $this->filters($request);

        $rows = $this->query($filters)
            ->with(['user.department', 'kiosk'])
            ->lazy()
            ->map(fn (Punch $punch): array => [
                $punch->punched_at->format('d/m/Y'),
                $punch->punched_at->format('H:i:s'),
                $punch->user->name,
                $punch->user->department->name ?? '',
                $punch->type->label(),
                $punch->kiosk->name ?? 'Saisie du gérant',
            ]);

        return $exporter->download(
            fileName: 'pointages-'.$filters['from']->format('Ymd').'-'.$filters['to']->format('Ymd').'.xlsx',
            title: 'Historique des pointages',
            subtitle: 'Du '.$filters['from']->format('d/m/Y').' au '.$filters['to']->format('d/m/Y'),
            headings: ['Date', 'Heure', 'Employé', 'Département', 'Action', 'Borne'],
            rows: $rows,
        );
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable, employee: ?int, department: ?int, type: ?PunchType}
     */
    private function filters(Request $request): array
    {
        $from = $this->date($request->string('du')->toString()) ?? CarbonImmutable::today()->subDays(6);
        $to = $this->date($request->string('au')->toString()) ?? CarbonImmutable::today();

        if ($from->isAfter($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'employee' => $request->integer('employe') ?: null,
            'department' => $request->integer('departement') ?: null,
            'type' => PunchType::tryFrom($request->string('action')->toString()),
        ];
    }

    /**
     * @param  array{from: CarbonImmutable, to: CarbonImmutable, employee: ?int, department: ?int, type: ?PunchType}  $filters
     * @return Builder<Punch>
     */
    private function query(array $filters): Builder
    {
        return Punch::query()
            ->whereBetween('punched_at', [$filters['from']->startOfDay(), $filters['to']->endOfDay()])
            ->when($filters['employee'], fn (Builder $query, int $employee) => $query->where('user_id', $employee))
            ->when($filters['department'], fn (Builder $query, int $department) => $query->whereHas(
                'user',
                fn (Builder $users) => $users->where('department_id', $department),
            ))
            ->when($filters['type'], fn (Builder $query, PunchType $type) => $query->where('type', $type))
            ->latest('punched_at');
    }

    private function date(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
