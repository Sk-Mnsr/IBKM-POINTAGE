@extends('pdf.layout')

@use('App\Support\Duration')
@use('App\Enums\AttendanceStatus')

@section('title', 'Fiche de présence · '.$employee->name)

@section('subtitle')
    {{ $month->label() }} · {{ $employee->department->name ?? 'Sans département' }} · {{ $employee->phone }}
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td><div class="label">Jours travaillés</div><div class="value">{{ $summary->workedDays }}</div></td>
            <td><div class="label">Heures travaillées</div><div class="value">{{ Duration::format($summary->workedMinutes, '0 h') }}</div></td>
            <td><div class="label">Retards</div><div class="value">{{ $summary->lateCount }} · {{ Duration::format($summary->lateMinutes, '0 min') }}</div></td>
            <td><div class="label">Heures sup</div><div class="value">{{ Duration::format($summary->overtimeMinutes, '0 h') }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Horaire prévu</th>
                <th>Arrivée</th>
                <th>Départ</th>
                <th class="right">Pause</th>
                <th class="right">Travaillé</th>
                <th class="right">Retard</th>
                <th class="right">Heures sup</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($days as $day)
                @php($faded = in_array($day->status, [AttendanceStatus::DayOff, AttendanceStatus::Upcoming], true))
                <tr class="{{ $faded ? 'faded' : '' }}">
                    <td>{{ ucfirst($day->date->translatedFormat('D d/m')) }}</td>
                    <td>{{ $day->scheduleLabel() }}</td>
                    <td>{{ $day->firstArrival?->format('H:i') ?? '—' }}</td>
                    <td>{{ $day->lastDeparture?->format('H:i') ?? '—' }}</td>
                    <td class="right">{{ Duration::format($day->breakMinutes) }}</td>
                    <td class="right">{{ Duration::format($day->workedMinutes) }}</td>
                    <td class="right {{ $day->isLate() ? 'late' : '' }}">{{ $day->isLate() ? $day->lateMinutes.' min' : '—' }}</td>
                    <td class="right">{{ Duration::format($day->overtimeMinutes) }}</td>
                    <td class="{{ $day->status === AttendanceStatus::Absent ? 'absent' : '' }}">{{ $day->status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">Total · {{ $summary->absentDays }} absence(s), {{ $summary->leaveDays }} jour(s) de congé</td>
                <td class="right">{{ Duration::format($summary->breakMinutes) }}</td>
                <td class="right">{{ Duration::format($summary->workedMinutes) }}</td>
                <td class="right">{{ Duration::format($summary->lateMinutes) }}</td>
                <td class="right">{{ Duration::format($summary->overtimeMinutes) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <table class="signatures">
        <tr>
            <td>Signature de l'employé</td>
            <td style="padding-left: 30px;">Signature du gérant</td>
        </tr>
    </table>
@endsection
