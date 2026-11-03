<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import CorrectionPanel from '@/components/CorrectionPanel.vue';
import DataTable from '@/components/DataTable.vue';
import Icon from '@/components/Icon.vue';
import MonthPicker from '@/components/MonthPicker.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDuration, formatRate } from '@/lib/format';
import type { AttendanceSummary, DailyAttendance, Employee, PunchCorrectionEntry, PunchTypeValue, ReportMonth } from '@/types';

const props = defineProps<{
    employee: Employee;
    month: ReportMonth;
    days: DailyAttendance[];
    summary: AttendanceSummary;
    corrections: PunchCorrectionEntry[];
    punchTypes: PunchTypeValue[];
    today: string;
}>();

const selectedDate = ref<string | null>(null);
const selectedDay = computed(() => props.days.find((day) => day.date === selectedDate.value) ?? null);
const correctionsFor = (date: string): PunchCorrectionEntry[] => props.corrections.filter((correction) => correction.date === date);
const incompleteDays = computed(() => props.days.filter((day) => day.status.value === 'incomplete'));

const parameters = computed(() => ({ employee: props.employee.id, mois: props.month.value }));

const rowClasses = (day: DailyAttendance): string => {
    if (day.status.value === 'absent') {
        return 'bg-rose-50/50';
    }

    if (day.status.value === 'incomplete') {
        return 'bg-amber-50/70';
    }

    return ['day_off', 'upcoming'].includes(day.status.value) ? 'text-ink/45' : '';
};
</script>

<template>
    <Head :title="employee.name" />

    <Link :href="route('reports.index', { mois: month.value })" class="inline-flex items-center gap-1 text-sm text-brand-dark">
        <Icon name="chevron-left" class="size-4" />
        Rapport mensuel
    </Link>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-center gap-4">
            <span class="grid size-14 place-items-center rounded-2xl bg-ink text-lg font-semibold text-cream">{{ employee.initials }}</span>
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">{{ employee.name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink/60">
                    {{ employee.department ?? 'Sans département' }} · {{ employee.phone }}
                    <StatusBadge v-if="!employee.isActive" tone="rose" label="Compte désactivé" />
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <Link :href="route('employees.edit', employee.id)" class="inline-flex h-11 items-center gap-2 rounded-full border border-line bg-paper px-4 text-sm font-medium">
                <Icon name="pencil" class="size-4" />
                Modifier
            </Link>
            <a :href="route('reports.employee.excel', parameters)" class="inline-flex h-11 items-center gap-2 rounded-full border border-line bg-paper px-4 text-sm font-medium">
                <Icon name="sheet" class="size-4 text-brand-dark" />
                Excel
            </a>
            <a :href="route('reports.employee.pdf', parameters)" class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-4 text-sm font-medium text-cream">
                <Icon name="pdf" class="size-4" />
                Fiche PDF
            </a>
        </div>
    </div>

    <MonthPicker class="mt-6" :month="month" route-name="reports.employee" :params="{ employee: employee.id }" />

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Jours travaillés" :value="summary.workedDays" icon="calendar" :hint="`${summary.absentDays} absence(s) · ${summary.leaveDays} congé(s)`" />
        <StatCard label="Heures travaillées" :value="formatDuration(summary.workedMinutes, '0 h')" icon="clock" tone="emerald" :hint="`Prévu : ${formatDuration(summary.scheduledMinutes, '0 h')}`" />
        <StatCard label="Retards" :value="summary.lateCount" icon="timer" tone="amber" :hint="`Cumul : ${formatDuration(summary.lateMinutes, '0 min')}`" />
        <StatCard label="Heures supplémentaires" :value="formatDuration(summary.overtimeMinutes, '0 h')" icon="trending" tone="brand" :hint="`Ponctualité : ${formatRate(summary.punctualityRate)}`" />
    </section>

    <div v-if="incompleteDays.length" class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <span class="flex items-center gap-2">
            <Icon name="alert" class="size-4" />
            {{ incompleteDays.length }} journée(s) sans départ pointé : ces heures ne sont pas comptées tant qu’elles ne sont pas corrigées.
        </span>
        <button type="button" class="rounded-full bg-amber-900 px-4 py-1.5 font-medium text-amber-50" @click="selectedDate = incompleteDays[0].date">Corriger</button>
    </div>

    <DataTable class="mt-6" min-width="56rem">
        <template #head>
            <tr>
                <th class="px-5 py-3 font-medium">Date</th>
                <th class="px-5 py-3 font-medium">Horaire prévu</th>
                <th class="px-5 py-3 font-medium">Arrivée</th>
                <th class="px-5 py-3 font-medium">Départ</th>
                <th class="px-5 py-3 text-right font-medium">Pause</th>
                <th class="px-5 py-3 text-right font-medium">Travaillé</th>
                <th class="px-5 py-3 text-right font-medium">Retard</th>
                <th class="px-5 py-3 text-right font-medium">Heures sup</th>
                <th class="px-5 py-3 font-medium">Statut</th>
                <th class="w-12 px-3 py-3"><span class="sr-only">Corriger</span></th>
            </tr>
        </template>
        <tr v-for="day in days" :key="day.date" class="hover:bg-cream/40" :class="rowClasses(day)">
            <td class="px-5 py-2.5 font-medium">{{ day.dateLabel }}</td>
            <td class="px-5 py-2.5 tabular-nums">{{ day.scheduleLabel }}</td>
            <td class="px-5 py-2.5 tabular-nums">{{ day.firstArrival ?? '—' }}</td>
            <td class="px-5 py-2.5 tabular-nums">{{ day.lastDeparture ?? '—' }}</td>
            <td class="px-5 py-2.5 text-right tabular-nums">{{ formatDuration(day.breakMinutes) }}</td>
            <td class="px-5 py-2.5 text-right font-medium tabular-nums">{{ formatDuration(day.workedMinutes) }}</td>
            <td class="px-5 py-2.5 text-right tabular-nums" :class="{ 'font-semibold text-amber-700': day.lateMinutes > 0 }">{{ day.lateMinutes > 0 ? `${day.lateMinutes} min` : '—' }}</td>
            <td class="px-5 py-2.5 text-right tabular-nums">{{ formatDuration(day.overtimeMinutes) }}</td>
            <td class="px-5 py-2.5">
                <span class="inline-flex items-center gap-1.5">
                    <StatusBadge :tone="day.status.tone" :label="day.status.label" />
                    <span v-if="correctionsFor(day.date).length" class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800" title="Journée corrigée par le gérant">Corrigé</span>
                </span>
            </td>
            <td class="px-3 py-2.5 text-right">
                <button
                    v-if="day.date <= today"
                    type="button"
                    class="grid size-8 place-items-center rounded-full text-ink/40 hover:bg-cream hover:text-ink"
                    :class="{ 'bg-amber-100 text-amber-800': day.status.value === 'incomplete' }"
                    :aria-label="`Corriger le ${day.dateLabel}`"
                    @click="selectedDate = day.date"
                >
                    <Icon name="pencil" class="size-4" />
                </button>
            </td>
        </tr>
    </DataTable>

    <section v-if="corrections.length" class="mt-6 rounded-3xl border border-line bg-paper p-5">
        <div class="flex items-center gap-2">
            <span class="grid size-8 place-items-center rounded-xl bg-violet-100 text-violet-800">
                <Icon name="history" class="size-4" />
            </span>
            <h2 class="font-semibold">Journal des corrections du mois</h2>
        </div>
        <ul class="mt-4 divide-y divide-line">
            <li v-for="correction in corrections" :key="correction.id" class="flex flex-wrap items-start justify-between gap-2 py-3 text-sm">
                <div>
                    <p>
                        <span class="font-medium">{{ correction.dateLabel }}</span>
                        · {{ correction.actionLabel }} · {{ correction.summary }}
                    </p>
                    <p class="mt-0.5 text-ink/55">« {{ correction.reason }} »</p>
                </div>
                <p class="text-xs text-ink/45">{{ correction.author }}, le {{ correction.at }}</p>
            </li>
        </ul>
    </section>

    <CorrectionPanel
        v-if="selectedDay"
        :employee-id="employee.id"
        :employee-name="employee.name"
        :day="selectedDay"
        :punch-types="punchTypes"
        :corrections="correctionsFor(selectedDay.date)"
        @close="selectedDate = null"
    />
</template>
