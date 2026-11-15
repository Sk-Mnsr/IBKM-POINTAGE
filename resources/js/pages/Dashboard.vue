<script setup lang="ts">
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import ProgressRing from '@/components/ProgressRing.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDuration, formatRate, punchCircleClasses, punchIcon } from '@/lib/format';
import type { AttendanceSummary, DailyAttendance, Employee, LeaveRequest, Punch } from '@/types';

interface Row {
    employee: Employee;
    day: DailyAttendance;
}

interface ChartDay {
    label: string;
    present: number;
    late: number;
    absent: number;
    expected: number;
}

const props = defineProps<{
    dateLabel: string;
    updatedAt: string;
    rows: Row[];
    counts: {
        onSite: number;
        onBreak: number;
        late: number;
        absent: number;
        expected: number;
        onLeave: number;
        scheduled: number;
        arrived: number;
        awaited: number;
        team: number;
    };
    breakdownTitle: string;
    departments: { name: string; team: number; awaited: number; arrived: number; onSite: number }[];
    chart: ChartDay[];
    monthSummary: AttendanceSummary;
    recentPunches: Punch[];
    pendingLeaves: LeaveRequest[];
    kiosks: { name: string; url: string }[];
}>();

const page = usePage();

usePoll(60_000);

const firstName = computed(() => page.props.auth.user?.name.split(' ')[0] ?? '');

const statusCount = (value: string): number => props.rows.filter((row) => row.day.status.value === value).length;

const breakdown = computed(() =>
    [
        { label: 'Au travail', value: statusCount('present'), color: 'bg-brand' },
        { label: 'En pause', value: statusCount('on_break'), color: 'bg-amber-400' },
        { label: 'Partis', value: statusCount('left'), color: 'bg-stone-400' },
        { label: 'Attendus', value: statusCount('expected'), color: 'bg-white/30' },
        { label: 'Absents', value: statusCount('absent'), color: 'bg-rose-400' },
        { label: 'En congé', value: statusCount('on_leave'), color: 'bg-sky-400' },
    ].filter((segment) => segment.value > 0),
);

const kpis = computed(() => [
    {
        label: 'Arrivés aujourd’hui',
        value: props.counts.arrived,
        hint: `${props.counts.onSite} sur place · ${props.counts.onBreak} en pause`,
        icon: 'users',
        accent: 'bg-brand/15 text-brand-dark',
        bar: 'bg-brand',
        series: props.chart.map((day) => day.present),
    },
    {
        label: 'Retards',
        value: props.counts.late,
        hint: `Au-delà de ${page.props.app.lateToleranceMinutes} min de tolérance`,
        icon: 'timer',
        accent: 'bg-amber-100 text-amber-800',
        bar: 'bg-amber-400',
        series: props.chart.map((day) => day.late),
    },
    {
        label: 'Absents',
        value: props.counts.absent,
        hint: props.counts.expected > 0 ? `${props.counts.expected} encore attendu(s)` : 'Aucune arrivée en attente',
        icon: 'user-x',
        accent: 'bg-rose-100 text-rose-800',
        bar: 'bg-rose-400',
        series: props.chart.map((day) => day.absent),
    },
    {
        label: 'En congé',
        value: props.counts.onLeave,
        hint: props.pendingLeaves.length > 0 ? `${props.pendingLeaves.length} demande(s) à traiter` : 'Aucune demande en attente',
        icon: 'plane',
        accent: 'bg-sky-100 text-sky-800',
        bar: 'bg-sky-400',
        series: [] as number[],
    },
]);

const sparkHeight = (value: number, series: number[]): string => `${Math.max(8, (value / Math.max(1, ...series)) * 100)}%`;

const filters = computed(() => [
    { key: 'all', label: 'Tous', count: props.rows.length },
    { key: 'on_site', label: 'Sur place', count: props.counts.onSite },
    { key: 'late', label: 'Retards', count: props.counts.late },
    { key: 'absent', label: 'Absents', count: props.counts.absent },
]);

const activeFilter = ref('all');
const search = ref('');

const visibleRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        if (term && !`${row.employee.name} ${row.employee.department ?? ''}`.toLowerCase().includes(term)) {
            return false;
        }

        switch (activeFilter.value) {
            case 'on_site':
                return ['present', 'on_break'].includes(row.day.status.value);
            case 'late':
                return row.day.lateMinutes > 0;
            case 'absent':
                return row.day.status.value === 'absent';
            default:
                return true;
        }
    });
});

const avatarPalette = [
    'bg-brand/20 text-brand-dark',
    'bg-amber-100 text-amber-800',
    'bg-sky-100 text-sky-800',
    'bg-rose-100 text-rose-800',
    'bg-violet-100 text-violet-800',
    'bg-stone-200 text-stone-700',
];

const avatarClasses = (department: string | null | undefined): string => {
    const key = department ?? '';
    const hash = [...key].reduce((total, character) => total + character.charCodeAt(0), 0);

    return avatarPalette[hash % avatarPalette.length];
};

const workedPercent = (day: DailyAttendance): number =>
    day.scheduledMinutes > 0 ? Math.min(100, Math.round((day.workedMinutes / day.scheduledMinutes) * 100)) : day.workedMinutes > 0 ? 100 : 0;

const chartMax = computed(() =>
    Math.max(1, ...props.chart.map((day) => Math.max(day.expected, day.present + day.absent))),
);
const chartHeight = (value: number): string => `${(value / chartMax.value) * 100}%`;
const chartTicks = computed(() => [chartMax.value, Math.round(chartMax.value / 2), 0]);
</script>

<template>
    <Head title="Tableau de bord" />

    <div class="grid gap-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-brand-dark">{{ dateLabel }}</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Bonjour {{ firstName }}, voici le salon en direct</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-11 items-center gap-2 rounded-full border border-line bg-paper px-4 text-sm text-ink/70">
                    <span class="relative flex size-2">
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-60" />
                        <span class="relative inline-flex size-2 rounded-full bg-brand" />
                    </span>
                    En direct · {{ updatedAt }}
                </span>
                <a
                    v-for="kiosk in kiosks"
                    :key="kiosk.url"
                    :href="kiosk.url"
                    target="_blank"
                    class="inline-flex h-11 items-center gap-2 rounded-full border border-line bg-paper px-4 text-sm font-medium hover:border-ink/30"
                >
                    <Icon name="qr" class="size-4" />
                    Borne {{ kiosk.name }}
                </a>
                <Link :href="route('employees.create')" class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-4 text-sm font-medium text-cream hover:bg-ink/90">
                    <Icon name="plus" class="size-4" />
                    Nouvel employé
                </Link>
            </div>
        </header>

        <section class="grid gap-4 xl:grid-cols-[24rem_minmax(0,1fr)]">
            <article class="relative overflow-hidden rounded-3xl bg-ink p-6 text-cream">
                <div class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-brand/20 blur-3xl" />
                <div class="relative flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-cream/60">Présence du jour</p>
                        <p class="mt-1 text-xl font-semibold">{{ counts.arrived }} sur {{ counts.awaited }} attendus</p>
                    </div>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs text-cream/70">{{ counts.team }} actifs</span>
                </div>

                <div class="relative mt-6 flex items-center gap-6">
                    <ProgressRing :value="counts.arrived" :total="counts.awaited">
                        <p class="text-[11px] tracking-wide text-cream/50 uppercase">présents</p>
                    </ProgressRing>
                    <ul class="grid flex-1 gap-2 text-sm">
                        <li v-for="segment in breakdown" :key="segment.label" class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 text-cream/70">
                                <span class="size-2.5 rounded-full" :class="segment.color" />
                                {{ segment.label }}
                            </span>
                            <span class="font-semibold tabular-nums">{{ segment.value }}</span>
                        </li>
                        <li v-if="breakdown.length === 0" class="text-cream/50">Aucune activité prévue aujourd’hui.</li>
                    </ul>
                </div>

                <div v-if="counts.team > 0" class="relative mt-6 flex h-2 overflow-hidden rounded-full bg-white/10">
                    <span
                        v-for="segment in breakdown"
                        :key="segment.label"
                        :class="segment.color"
                        :style="{ width: `${(segment.value / counts.team) * 100}%` }"
                    />
                </div>
            </article>

            <div class="grid gap-4 sm:grid-cols-2">
                <article v-for="kpi in kpis" :key="kpi.label" class="flex flex-col justify-between gap-4 rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-ink/60">{{ kpi.label }}</p>
                            <p class="mt-1 text-4xl font-semibold tracking-tight tabular-nums">{{ kpi.value }}</p>
                        </div>
                        <span class="grid size-11 place-items-center rounded-2xl" :class="kpi.accent">
                            <Icon :name="kpi.icon" />
                        </span>
                    </div>
                    <div class="flex items-end justify-between gap-4">
                        <p class="text-xs text-ink/55">{{ kpi.hint }}</p>
                        <div v-if="kpi.series.length" class="flex h-9 items-end gap-1" :title="`7 derniers jours : ${kpi.series.join(', ')}`">
                            <span
                                v-for="(value, index) in kpi.series"
                                :key="index"
                                class="w-1.5 rounded-full"
                                :class="index === kpi.series.length - 1 ? kpi.bar : 'bg-ink/10'"
                                :style="{ height: sparkHeight(value, kpi.series) }"
                            />
                        </div>
                        <Link v-else :href="route('leaves.index')" class="inline-flex items-center gap-1 text-xs font-medium text-brand-dark">
                            Gérer
                            <Icon name="chevron-right" class="size-3.5" />
                        </Link>
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <article class="overflow-hidden rounded-3xl border border-line bg-paper">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="flex flex-wrap items-center gap-1 rounded-full bg-cream p-1 text-sm">
                        <button
                            v-for="filter in filters"
                            :key="filter.key"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 transition"
                            :class="activeFilter === filter.key ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                            @click="activeFilter = filter.key"
                        >
                            {{ filter.label }}
                            <span class="rounded-full px-1.5 text-xs tabular-nums" :class="activeFilter === filter.key ? 'bg-white/15' : 'bg-ink/5'">{{ filter.count }}</span>
                        </button>
                    </div>
                    <label class="relative">
                        <span class="sr-only">Rechercher un employé</span>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Rechercher…"
                            class="h-10 w-48 rounded-full border border-line bg-cream/60 pr-4 pl-4 text-sm outline-none focus:border-brand focus:bg-white"
                        >
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-left text-sm whitespace-nowrap">
                        <thead class="text-xs tracking-wide text-ink/45 uppercase">
                            <tr>
                                <th class="px-5 py-3 font-medium">Employé</th>
                                <th class="px-5 py-3 font-medium">Horaire</th>
                                <th class="px-5 py-3 font-medium">Arrivée</th>
                                <th class="px-5 py-3 font-medium">Temps travaillé</th>
                                <th class="px-5 py-3 text-right font-medium">État</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr v-for="row in visibleRows" :key="row.employee.id" class="transition hover:bg-cream/50">
                                <td class="px-5 py-3.5">
                                    <Link :href="route('reports.employee', row.employee.id)" class="group flex items-center gap-3">
                                        <span class="grid size-10 place-items-center rounded-2xl text-xs font-semibold" :class="avatarClasses(row.employee.department)">
                                            {{ row.employee.initials }}
                                        </span>
                                        <span>
                                            <span class="block font-medium group-hover:text-brand-dark">{{ row.employee.name }}</span>
                                            <span class="block text-xs text-ink/50">{{ row.employee.department ?? 'Sans département' }}</span>
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-5 py-3.5 tabular-nums text-ink/60">{{ row.day.scheduleLabel }}</td>
                                <td class="px-5 py-3.5 tabular-nums">
                                    <span v-if="row.day.firstArrival" class="inline-flex items-center gap-2">
                                        <span class="font-medium">{{ row.day.firstArrival }}</span>
                                        <span v-if="row.day.lateMinutes > 0" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                            +{{ formatDuration(row.day.lateMinutes) }}
                                        </span>
                                    </span>
                                    <span v-else class="text-ink/30">—</span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-ink/5">
                                            <div class="h-full rounded-full bg-brand transition-[width] duration-500" :style="{ width: `${workedPercent(row.day)}%` }" />
                                        </div>
                                        <span class="text-xs tabular-nums text-ink/60">{{ formatDuration(row.day.workedMinutes, '0 min') }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <StatusBadge :tone="row.day.status.tone" :label="row.day.status.label" />
                                </td>
                            </tr>
                            <tr v-if="visibleRows.length === 0">
                                <td colspan="5" class="px-5 py-12 text-center text-ink/50">
                                    <template v-if="rows.length === 0">
                                        Aucun employé actif.
                                        <Link :href="route('employees.create')" class="font-medium text-brand-dark">Ajouter un employé</Link>
                                    </template>
                                    <template v-else>Personne ne correspond à ce filtre.</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <div class="grid content-start gap-6">
                <article class="rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Activité du jour</h2>
                        <Link :href="route('punches.index')" class="text-sm font-medium text-brand-dark">Historique</Link>
                    </div>
                    <ol class="relative mt-5 grid gap-4 before:absolute before:top-2 before:bottom-2 before:left-4 before:w-px before:bg-line">
                        <li v-for="punch in recentPunches" :key="punch.id" class="relative flex items-center gap-3 text-sm">
                            <span class="relative z-10 grid size-8 place-items-center rounded-full ring-4 ring-paper" :class="punchCircleClasses(punch.type.value)">
                                <Icon :name="punchIcon(punch.type.value)" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ punch.employee?.name }}</span>
                                <span class="block text-xs text-ink/50">{{ punch.type.label }}</span>
                            </span>
                            <span class="rounded-full bg-cream px-2 py-0.5 text-xs font-medium tabular-nums text-ink/60">{{ punch.time }}</span>
                        </li>
                        <li v-if="recentPunches.length === 0" class="relative pl-12 text-sm text-ink/50">Aucun pointage aujourd’hui.</li>
                    </ol>
                </article>

                <article class="rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Congés à traiter</h2>
                        <span v-if="pendingLeaves.length" class="rounded-full bg-brand/15 px-2.5 py-0.5 text-xs font-semibold text-brand-dark">{{ pendingLeaves.length }}</span>
                    </div>
                    <ul class="mt-4 grid gap-2">
                        <li v-for="leave in pendingLeaves" :key="leave.id">
                            <Link :href="route('leaves.index', { statut: 'pending' })" class="flex items-center gap-3 rounded-2xl bg-cream/70 px-3 py-2.5 text-sm hover:bg-cream">
                                <span class="grid size-8 place-items-center rounded-full bg-paper text-xs font-semibold">{{ leave.employee?.initials }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ leave.employee?.name }}</span>
                                    <span class="block text-xs text-ink/50">{{ leave.type.label }} · {{ leave.shortPeriod }}</span>
                                </span>
                                <Icon name="chevron-right" class="size-4 text-ink/30" />
                            </Link>
                        </li>
                        <li v-if="pendingLeaves.length === 0" class="rounded-2xl bg-cream/70 px-3 py-3 text-sm text-ink/50">Tout est à jour.</li>
                    </ul>
                </article>

                <article class="rounded-3xl border border-line bg-paper p-5">
                    <h2 class="font-semibold">{{ breakdownTitle }}</h2>
                    <ul class="mt-4 grid gap-4">
                        <li v-for="department in departments" :key="department.name" class="text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ department.name }}</span>
                                <span class="tabular-nums text-ink/55">{{ department.arrived }} / {{ department.awaited }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink/5">
                                <div
                                    class="h-full rounded-full bg-brand"
                                    :style="{ width: `${department.awaited > 0 ? (department.arrived / department.awaited) * 100 : 0}%` }"
                                />
                            </div>
                        </li>
                        <li v-if="departments.length === 0" class="text-sm text-ink/50">Aucun département.</li>
                    </ul>
                </article>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <article class="rounded-3xl border border-line bg-paper p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Présence sur 7 jours</h2>
                        <p class="text-xs text-ink/50">Répartition réelle : à l’heure, en retard, absents</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-ink/60 sm:gap-4">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand" />À l’heure</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-amber-400" />En retard</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-rose-300" />Absents</span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-[2rem_minmax(0,1fr)] gap-3">
                    <div class="flex h-52 flex-col justify-between text-right text-xs tabular-nums text-ink/40">
                        <span v-for="(tick, index) in chartTicks" :key="index">{{ tick }}</span>
                    </div>
                    <div class="relative h-52">
                        <div class="absolute inset-0 flex flex-col justify-between">
                            <span v-for="(tick, index) in chartTicks" :key="index" class="border-t border-dashed border-line" />
                        </div>
                        <div class="relative grid h-full grid-cols-7 items-end gap-3 sm:gap-5">
                            <div
                                v-for="day in chart"
                                :key="day.label"
                                class="group relative flex h-full items-end justify-center"
                                :title="`${day.label} : ${Math.max(0, day.present - day.late)} à l’heure, ${day.late} retard(s), ${day.absent} absent(s) · ${day.expected} attendu(s)`"
                            >
                                <div
                                    class="relative flex w-full max-w-10 flex-col-reverse overflow-hidden rounded-t-xl transition-all duration-500"
                                    :style="{ height: chartHeight(Math.max(day.expected, day.present + day.absent, 1)) }"
                                >
                                    <div
                                        class="bg-brand"
                                        :style="{
                                            height: `${day.expected > 0 ? (Math.max(0, day.present - day.late) / Math.max(day.expected, day.present + day.absent, 1)) * 100 : 0}%`,
                                        }"
                                    />
                                    <div
                                        class="bg-amber-400"
                                        :style="{
                                            height: `${day.expected > 0 ? (day.late / Math.max(day.expected, day.present + day.absent, 1)) * 100 : 0}%`,
                                        }"
                                    />
                                    <div
                                        class="bg-rose-300"
                                        :style="{
                                            height: `${day.expected > 0 ? (day.absent / Math.max(day.expected, day.present + day.absent, 1)) * 100 : 0}%`,
                                        }"
                                    />
                                </div>
                                <span class="pointer-events-none absolute -top-7 rounded-md bg-ink px-2 py-0.5 text-xs text-cream opacity-0 transition group-hover:opacity-100">
                                    {{ day.present }}/{{ day.expected }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <span />
                    <div class="grid grid-cols-7 gap-3 text-center text-xs text-ink/50 sm:gap-5">
                        <span v-for="day in chart" :key="day.label">{{ day.label }}</span>
                    </div>
                </div>
            </article>

            <article class="relative overflow-hidden rounded-3xl bg-ink p-5 text-cream">
                <div class="pointer-events-none absolute -bottom-24 -left-16 size-56 rounded-full bg-brand/15 blur-3xl" />
                <div class="relative">
                    <h2 class="font-semibold">Mois en cours</h2>
                    <p class="text-xs text-cream/50">Depuis le 1er du mois</p>

                    <div class="mt-5">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm text-cream/60">Ponctualité</span>
                            <span class="text-2xl font-semibold tabular-nums">{{ formatRate(monthSummary.punctualityRate) }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-brand" :style="{ width: `${monthSummary.punctualityRate ?? 0}%` }" />
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl bg-white/5 p-3">
                            <dt class="text-xs text-cream/50">Heures travaillées</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ formatDuration(monthSummary.workedMinutes, '0 h') }}</dd>
                        </div>
                        <div class="rounded-2xl bg-white/5 p-3">
                            <dt class="text-xs text-cream/50">Heures sup</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ formatDuration(monthSummary.overtimeMinutes, '0 h') }}</dd>
                        </div>
                        <div class="rounded-2xl bg-white/5 p-3">
                            <dt class="text-xs text-cream/50">Retards</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ monthSummary.lateCount }} · {{ formatDuration(monthSummary.lateMinutes, '0 min') }}</dd>
                        </div>
                        <div class="rounded-2xl bg-white/5 p-3">
                            <dt class="text-xs text-cream/50">Absences</dt>
                            <dd class="mt-1 font-semibold tabular-nums">{{ monthSummary.absentDays }}</dd>
                        </div>
                    </dl>

                    <Link :href="route('reports.index')" class="mt-5 inline-flex h-10 w-full items-center justify-center gap-2 rounded-full bg-brand text-sm font-semibold text-ink hover:bg-brand/90">
                        <Icon name="chart" class="size-4" />
                        Voir le rapport mensuel
                    </Link>
                </div>
            </article>
        </section>
    </div>
</template>
