@extends('pdf.layout')

@use('App\Support\Duration')

@section('title', 'Récapitulatif mensuel de présence')

@section('subtitle')
    {{ $month->label() }}@if ($department) · {{ $department->name }}@endif · {{ $rows->count() }} employé(s)
@endsection

@section('content')
    <table class="cards">
        <tr>
            <td><div class="label">Heures travaillées</div><div class="value">{{ Duration::format($totals->workedMinutes, '0 h') }}</div></td>
            <td><div class="label">Heures sup</div><div class="value">{{ Duration::format($totals->overtimeMinutes, '0 h') }}</div></td>
            <td><div class="label">Retards</div><div class="value">{{ $totals->lateCount }}</div></td>
            <td><div class="label">Absences</div><div class="value">{{ $totals->absentDays }}</div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Employé</th>
                <th>Département</th>
                <th class="right">Jours</th>
                <th class="right">Absences</th>
                <th class="right">Congés</th>
                <th class="right">Retards</th>
                <th class="right">Cumul retard</th>
                <th class="right">Prévu</th>
                <th class="right">Travaillé</th>
                <th class="right">Heures sup</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                @php($summary = $row['summary'])
                <tr>
                    <td><strong>{{ $row['employee']->name }}</strong></td>
                    <td>{{ $row['employee']->department->name ?? '—' }}</td>
                    <td class="right">{{ $summary->workedDays }}</td>
                    <td class="right {{ $summary->absentDays > 0 ? 'absent' : '' }}">{{ $summary->absentDays }}</td>
                    <td class="right">{{ $summary->leaveDays }}</td>
                    <td class="right {{ $summary->lateCount > 0 ? 'late' : '' }}">{{ $summary->lateCount }}</td>
                    <td class="right">{{ Duration::format($summary->lateMinutes) }}</td>
                    <td class="right">{{ Duration::format($summary->scheduledMinutes) }}</td>
                    <td class="right"><strong>{{ Duration::format($summary->workedMinutes) }}</strong></td>
                    <td class="right">{{ Duration::format($summary->overtimeMinutes) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2">Total équipe</td>
                <td class="right">{{ $totals->workedDays }}</td>
                <td class="right">{{ $totals->absentDays }}</td>
                <td class="right">{{ $totals->leaveDays }}</td>
                <td class="right">{{ $totals->lateCount }}</td>
                <td class="right">{{ Duration::format($totals->lateMinutes) }}</td>
                <td class="right">{{ Duration::format($totals->scheduledMinutes) }}</td>
                <td class="right">{{ Duration::format($totals->workedMinutes) }}</td>
                <td class="right">{{ Duration::format($totals->overtimeMinutes) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
