<?php

namespace App\Http\Controllers;

use App\Enums\PunchType;
use App\Http\Resources\EmployeeResource;
use App\Models\PunchCorrection;
use App\Models\User;
use App\Support\AttendanceCalculator;
use App\Support\AttendanceSummary;
use App\Support\DailyAttendance;
use App\Support\Duration;
use App\Support\ReportMonth;
use App\Support\SpreadsheetExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeReportController extends Controller
{
    public function __construct(private AttendanceCalculator $calculator) {}

    public function show(Request $request, User $employee): InertiaResponse
    {
        $report = $this->report($request, $employee);

        return Inertia::render('Reports/Employee', [
            'employee' => (new EmployeeResource($employee))->resolve(),
            'month' => $report['month']->toArray(),
            'days' => $report['days']->map(fn (DailyAttendance $day): array => $day->toArray())->values()->all(),
            'summary' => $report['summary']->toArray(),
            'corrections' => PunchCorrection::query()
                ->with('author:id,name')
                ->where('user_id', $employee->id)
                ->whereDate('punch_date', '>=', $report['month']->start()->toDateString())
                ->whereDate('punch_date', '<=', $report['month']->end()->toDateString())
                ->latest()
                ->get()
                ->map(fn (PunchCorrection $correction): array => [
                    'id' => $correction->id,
                    'date' => $correction->punch_date->toDateString(),
                    'dateLabel' => ucfirst($correction->punch_date->translatedFormat('D d/m')),
                    'action' => $correction->action,
                    'actionLabel' => $correction->actionLabel(),
                    'summary' => $correction->summary(),
                    'reason' => $correction->reason,
                    'author' => $correction->author->name ?? 'Gérant',
                    'at' => $correction->created_at->format('d/m/Y à H:i'),
                ])
                ->all(),
            'punchTypes' => array_map(fn (PunchType $type): array => $type->toArray(), PunchType::cases()),
            'today' => today()->toDateString(),
        ]);
    }

    public function excel(Request $request, User $employee, SpreadsheetExporter $exporter): BinaryFileResponse
    {
        $report = $this->report($request, $employee);
        $summary = $report['summary'];

        $rows = $report['days']->map(fn (DailyAttendance $day): array => [
            ucfirst($day->date->translatedFormat('D d/m/Y')),
            $day->scheduleLabel(),
            $day->firstArrival?->format('H:i') ?? '',
            $day->lastDeparture?->format('H:i') ?? '',
            Duration::decimalHours($day->breakMinutes),
            Duration::decimalHours($day->workedMinutes),
            $day->lateMinutes,
            Duration::decimalHours($day->overtimeMinutes),
            $day->status->label(),
        ])->values()->all();

        return $exporter->download(
            fileName: 'fiche-'.Str::slug($employee->name).'-'.$report['month']->value().'.xlsx',
            title: 'Fiche de présence · '.$employee->name,
            subtitle: $report['month']->label().($employee->department ? ' · '.$employee->department->name : ''),
            headings: ['Date', 'Horaire prévu', 'Arrivée', 'Départ', 'Pause (h)', 'Travaillé (h)', 'Retard (min)', 'Heures sup (h)', 'Statut'],
            rows: $rows,
            totals: [
                'Total',
                Duration::decimalHours($summary->scheduledMinutes).' h prévues',
                $summary->workedDays.' jours',
                '',
                Duration::decimalHours($summary->breakMinutes),
                Duration::decimalHours($summary->workedMinutes),
                $summary->lateMinutes,
                Duration::decimalHours($summary->overtimeMinutes),
                $summary->absentDays.' absence(s)',
            ],
        );
    }

    public function pdf(Request $request, User $employee): Response
    {
        $report = $this->report($request, $employee);

        return Pdf::loadView('pdf.employee-report', $report)
            ->setOption('isFontSubsettingEnabled', true)
            ->setPaper('a4', 'portrait')
            ->download('fiche-'.Str::slug($employee->name).'-'.$report['month']->value().'.pdf');
    }

    /**
     * @return array{employee: User, month: ReportMonth, days: Collection<string, DailyAttendance>, summary: AttendanceSummary}
     */
    private function report(Request $request, User $employee): array
    {
        $employee->loadMissing(['department', 'workSchedules']);
        $month = ReportMonth::fromRequest($request);
        $days = $this->calculator->forUser($employee, $month->start(), $month->end());

        return [
            'employee' => $employee,
            'month' => $month,
            'days' => $days,
            'summary' => AttendanceSummary::fromDays($days),
        ];
    }
}
