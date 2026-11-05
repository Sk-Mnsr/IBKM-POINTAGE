<?php

namespace App\Http\Controllers;

use App\Http\Resources\DepartmentResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Shop;
use App\Models\User;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceSummary;
use App\Support\Duration;
use App\Support\ReportMonth;
use App\Support\ShopContext;
use App\Support\SpreadsheetExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private AttendanceCalculator $calculator, private ShopContext $shops) {}

    public function index(Request $request): InertiaResponse
    {
        $report = $this->report($request);

        return Inertia::render('Reports/Index', [
            'month' => $report['month']->toArray(),
            'departmentId' => $report['department']?->id,
            'departments' => DepartmentResource::collection(
                Department::query()->whereIn('shop_id', $this->shops->scopeIds())->orderBy('name')->get(),
            )->resolve(),
            'rows' => $report['rows']->map(fn (array $row): array => [
                'employee' => (new EmployeeResource($row['employee']))->resolve(),
                'summary' => $row['summary']->toArray(),
            ])->all(),
            'totals' => $report['totals']->toArray(),
        ]);
    }

    public function excel(Request $request, SpreadsheetExporter $exporter): BinaryFileResponse
    {
        $report = $this->report($request);

        $rows = $report['rows']->map(fn (array $row): array => [
            $row['employee']->name,
            $row['employee']->department->name ?? '',
            $row['summary']->workedDays,
            $row['summary']->absentDays,
            $row['summary']->leaveDays,
            $row['summary']->lateCount,
            Duration::decimalHours($row['summary']->lateMinutes),
            Duration::decimalHours($row['summary']->scheduledMinutes),
            Duration::decimalHours($row['summary']->workedMinutes),
            Duration::decimalHours($row['summary']->overtimeMinutes),
        ])->all();

        $totals = $report['totals'];

        return $exporter->download(
            fileName: 'rapport-mensuel-'.$report['month']->value().'.xlsx',
            title: 'Récapitulatif mensuel de présence',
            subtitle: $report['month']->label()
                .($report['shop'] ? ' · '.$report['shop']->name : '')
                .($report['department'] ? ' · '.$report['department']->name : ''),
            headings: ['Employé', 'Département', 'Jours travaillés', 'Absences', 'Congés', 'Retards', 'Retards (h)', 'Heures prévues', 'Heures travaillées', 'Heures sup'],
            rows: $rows,
            totals: [
                'Total',
                '',
                $totals->workedDays,
                $totals->absentDays,
                $totals->leaveDays,
                $totals->lateCount,
                Duration::decimalHours($totals->lateMinutes),
                Duration::decimalHours($totals->scheduledMinutes),
                Duration::decimalHours($totals->workedMinutes),
                Duration::decimalHours($totals->overtimeMinutes),
            ],
        );
    }

    public function pdf(Request $request): Response
    {
        $report = $this->report($request);

        return Pdf::loadView('pdf.monthly-report', $report)
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper('a4', 'landscape')
            ->download('rapport-mensuel-'.$report['month']->value().'.pdf');
    }

    /**
     * @return array{month: ReportMonth, shop: ?Shop, department: ?Department, rows: Collection<int, array{employee: User, summary: AttendanceSummary}>, totals: AttendanceSummary}
     */
    private function report(Request $request): array
    {
        $month = ReportMonth::fromRequest($request);
        $department = Department::query()
            ->whereIn('shop_id', $this->shops->scopeIds())
            ->find($request->integer('departement') ?: null);

        $employees = User::query()
            ->staff()
            ->whereIn('shop_id', $this->shops->scopeIds())
            ->with('department')
            ->when($department, fn ($query) => $query->where('department_id', $department->id))
            ->orderBy('name')
            ->get();

        $periods = $this->calculator->forPeriod($employees, $month->start(), $month->end());

        $rows = $employees
            ->map(fn (User $employee): array => [
                'employee' => $employee,
                'summary' => AttendanceSummary::fromDays($periods->get($employee->id)),
            ])
            ->filter(fn (array $row): bool => $row['employee']->is_active || $row['summary']->workedDays > 0)
            ->values();

        return [
            'month' => $month,
            'shop' => $this->shops->current(),
            'department' => $department,
            'rows' => $rows,
            'totals' => AttendanceSummary::combine($rows->pluck('summary')),
        ];
    }
}
