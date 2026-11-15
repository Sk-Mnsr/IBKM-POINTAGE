<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import MonthPicker from '@/components/MonthPicker.vue';
import ProgressRing from '@/components/ProgressRing.vue';
import { formatDuration, formatRate } from '@/lib/format';
import type { AttendanceSummary, Department, Employee, ReportMonth } from '@/types';

interface Row {
    employee: Employee;
    summary: AttendanceSummary;
}

type SortKey = 'name' | 'worked' | 'late' | 'absent' | 'punctuality';

const props = defineProps<{
    month: ReportMonth;
    departmentId: number | null;
    departments: Department[];
    rows: Row[];
    totals: AttendanceSummary;
}>();

const departmentFilter = computed(() => (props.departmentId ? { departement: props.departmentId } : {}));
const exportParams = computed(() => ({ mois: props.month.value, ...departmentFilter.value }));

const selectDepartment = (id: number | null): void => {
    router.get(route('reports.index'), { mois: props.month.value, ...(id ? { departement: id } : {}) }, { preserveScroll: true });
};

const percentOf = (value: number, total: number): number => (total > 0 ? Math.min(100, Math.round((value / total) * 100)) : 0);

const kpis = computed(() => [
    {
        label: 'Heures travaillées',
        value: formatDuration(props.totals.workedMinutes, '0 h'),
        hint: `sur ${formatDuration(props.totals.scheduledMinutes, '0 h')} prévues`,
        icon: 'clock',
        accent: 'bg-ink text-cream',
        progress: percentOf(props.totals.workedMinutes, props.totals.scheduledMinutes),
        bar: 'bg-brand',
    },
    {
        label: 'Heures supplémentaires',
        value: formatDuration(props.totals.overtimeMinutes, '0 h'),
        hint: `${props.rows.filter((row) => row.summary.overtimeMinutes > 0).length} employé(s) concerné(s)`,
        icon: 'trending',
        accent: 'bg-brand/15 text-brand-dark',
        progress: null,
        bar: 'bg-brand',
    },
    {
        label: 'Retards',
        value: String(props.totals.lateCount),
        hint: `${formatDuration(props.totals.lateMinutes, '0 min')} cumulées`,
        icon: 'timer',
        accent: 'bg-amber-100 text-amber-800',
        progress: percentOf(props.totals.lateCount, props.totals.workedDays),
        bar: 'bg-amber-400',
    },
    {
        label: 'Absences',
        value: String(props.totals.absentDays),
        hint: `${props.totals.leaveDays} jour(s) de congé à côté`,
        icon: 'user-x',
        accent: 'bg-rose-100 text-rose-800',
        progress: percentOf(props.totals.absentDays, props.totals.workedDays + props.totals.absentDays),
        bar: 'bg-rose-400',
    },
]);

const sortKey = ref<SortKey>('name');
const sortDescending = ref(false);
const search = ref('');
const listFilter = ref<'active' | 'all'>('active');

const sortValue = (row: Row, key: SortKey): number | string => {
    switch (key) {
        case 'worked':
            return row.summary.workedMinutes;
        case 'late':
            return row.summary.lateMinutes;
        case 'absent':
            return row.summary.absentDays;
        case 'punctuality':
            return row.summary.punctualityRate ?? -1;
        default:
            return row.employee.name.toLowerCase();
    }
};

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.rows.filter((row) => {
        if (term && !`${row.employee.name} ${row.employee.department ?? ''}`.toLowerCase().includes(term)) {
            return false;
        }

        if (listFilter.value === 'active') {
            return row.summary.workedMinutes > 0 || row.summary.absentDays > 0 || row.summary.lateCount > 0 || row.summary.leaveDays > 0;
        }

        return true;
    });
});

const sortedRows = computed(() =>
    [...filteredRows.value].sort((first, second) => {
        const a = sortValue(first, sortKey.value);
        const b = sortValue(second, sortKey.value);
        const order = a < b ? -1 : a > b ? 1 : 0;

        return sortDescending.value ? -order : order;
    }),
);

const activeCount = computed(
    () => props.rows.filter((row) => row.summary.workedMinutes > 0 || row.summary.absentDays > 0 || row.summary.lateCount > 0 || row.summary.leaveDays > 0).length,
);

const sortBy = (key: SortKey): void => {
    if (sortKey.value === key) {
        sortDescending.value = !sortDescending.value;

        return;
    }

    sortKey.value = key;
    sortDescending.value = key !== 'name';
};

const columns: { key: SortKey; label: string; align: string }[] = [
    { key: 'name', label: 'Employé', align: 'text-left' },
    { key: 'worked', label: 'Heures', align: 'text-left' },
    { key: 'absent', label: 'Présence', align: 'text-left' },
    { key: 'late', label: 'Retards', align: 'text-left' },
    { key: 'punctuality', label: 'Ponctualité', align: 'text-left' },
];

const punctualityTone = (rate: number | null): { bar: string; text: string } => {
    if (rate === null) {
        return { bar: 'bg-ink/10', text: 'text-ink/40' };
    }

    if (rate >= 90) {
        return { bar: 'bg-brand', text: 'text-brand-dark' };
    }

    if (rate >= 75) {
        return { bar: 'bg-amber-400', text: 'text-amber-700' };
    }

    return { bar: 'bg-rose-400', text: 'text-rose-700' };
};

const mostPunctual = computed(() =>
    [...props.rows]
        .filter((row) => row.summary.workedDays > 0)
        .sort((a, b) => (b.summary.punctualityRate ?? 0) - (a.summary.punctualityRate ?? 0) || a.summary.lateMinutes - b.summary.lateMinutes)
        .slice(0, 3),
);

const toWatch = computed(() =>
    [...props.rows]
        .filter((row) => row.summary.absentDays > 0 || row.summary.lateCount > 0)
        .sort((a, b) => b.summary.absentDays * 120 + b.summary.lateMinutes - (a.summary.absentDays * 120 + a.summary.lateMinutes))
        .slice(0, 3),
);
</script>

<template>
    <Head title="Rapports" />

    <div class="grid gap-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-brand-dark">Rapports</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Bilan de {{ month.label }}</h1>
                <p class="mt-1 text-sm text-ink/55">Heures, retards, absences et heures supplémentaires, arrêtés à aujourd’hui.</p>
            </div>
            <div class="flex items-center gap-1 rounded-full border border-line bg-paper p-1">
                <a :href="route('reports.excel', exportParams)" class="inline-flex h-9 items-center gap-2 rounded-full px-4 text-sm font-medium hover:bg-cream">
                    <Icon name="sheet" class="size-4 text-brand-dark" />
                    Excel
                </a>
                <a :href="route('reports.pdf', exportParams)" class="inline-flex h-9 items-center gap-2 rounded-full bg-ink px-4 text-sm font-medium text-cream hover:bg-ink/90">
                    <Icon name="pdf" class="size-4" />
                    PDF
                </a>
            </div>
        </header>

        <div class="flex flex-wrap items-center gap-3">
            <MonthPicker :month="month" route-name="reports.index" :params="departmentFilter" />
            <div class="flex flex-wrap items-center gap-1 rounded-full border border-line bg-paper p-1 text-sm">
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 transition"
                    :class="departmentId === null ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                    @click="selectDepartment(null)"
                >
                    Tous
                </button>
                <button
                    v-for="department in departments"
                    :key="department.id"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 transition"
                    :class="departmentId === department.id ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                    @click="selectDepartment(department.id)"
                >
                    {{ department.name }}
                </button>
            </div>
        </div>

        <section class="grid gap-4 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <article class="relative overflow-hidden rounded-3xl bg-ink p-6 text-cream">
                <div class="pointer-events-none absolute -top-24 -right-20 size-60 rounded-full bg-brand/20 blur-3xl" />
                <div class="relative">
                    <p class="text-sm text-cream/60">Taux de présence</p>
                    <p class="mt-1 text-lg font-semibold">{{ totals.workedDays }} jours travaillés · {{ totals.absentDays }} absence(s)</p>
                </div>
                <div class="relative mt-6 flex items-center gap-6">
                    <ProgressRing :value="totals.workedDays" :total="totals.workedDays + totals.absentDays">
                        <p class="text-[11px] tracking-wide text-cream/50 uppercase">présence</p>
                    </ProgressRing>
                    <div class="grid flex-1 gap-4 text-sm whitespace-nowrap">
                        <div>
                            <div class="flex items-center justify-between text-cream/60">
                                <span>Ponctualité</span>
                                <span class="font-semibold text-cream tabular-nums">{{ formatRate(totals.punctualityRate) }}</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-brand" :style="{ width: `${totals.punctualityRate ?? 0}%` }" />
                            </div>
                        </div>
                        <div class="flex items-center justify-between text-cream/60">
                            <span>Employés</span>
                            <span class="font-semibold text-cream tabular-nums">{{ rows.length }}</span>
                        </div>
                        <div class="flex items-center justify-between text-cream/60">
                            <span>Pauses</span>
                            <span class="font-semibold text-cream tabular-nums">{{ formatDuration(totals.breakMinutes, '0 h') }}</span>
                        </div>
                    </div>
                </div>
            </article>

            <div class="grid gap-4 sm:grid-cols-2">
                <article v-for="kpi in kpis" :key="kpi.label" class="flex flex-col justify-between gap-4 rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm text-ink/60">{{ kpi.label }}</p>
                            <p class="mt-1 text-3xl font-semibold tracking-tight tabular-nums">{{ kpi.value }}</p>
                        </div>
                        <span class="grid size-11 place-items-center rounded-2xl" :class="kpi.accent">
                            <Icon :name="kpi.icon" />
                        </span>
                    </div>
                    <div>
                        <div v-if="kpi.progress !== null" class="mb-2 h-1.5 overflow-hidden rounded-full bg-ink/5">
                            <div class="h-full rounded-full" :class="kpi.bar" :style="{ width: `${kpi.progress}%` }" />
                        </div>
                        <p class="text-xs text-ink/55">{{ kpi.hint }}</p>
                    </div>
                </article>
            </div>
        </section>

        <section class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_20rem]">
            <article class="overflow-hidden rounded-3xl border border-line bg-paper">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold">Détail par employé</h2>
                        <div class="flex items-center gap-1 rounded-full bg-cream p-1 text-xs">
                            <button
                                type="button"
                                class="rounded-full px-2.5 py-1 transition"
                                :class="listFilter === 'active' ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                                @click="listFilter = 'active'"
                            >
                                Avec activité · {{ activeCount }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-2.5 py-1 transition"
                                :class="listFilter === 'all' ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                                @click="listFilter = 'all'"
                            >
                                Tous · {{ rows.length }}
                            </button>
                        </div>
                    </div>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Rechercher…"
                        class="h-9 w-44 rounded-full border border-line bg-cream/60 px-4 text-sm outline-none focus:border-brand focus:bg-white"
                    >
                </div>
                <div class="max-h-[28rem] overflow-auto xl:max-h-[36rem]">
                    <table class="w-full min-w-[42rem] text-sm whitespace-nowrap">
                        <thead class="sticky top-0 z-10 border-b border-line bg-paper text-xs tracking-wide text-ink/45 uppercase">
                            <tr>
                                <th v-for="column in columns" :key="column.key" class="px-4 py-2.5 font-medium" :class="column.align">
                                    <button type="button" class="inline-flex items-center gap-1 uppercase hover:text-ink" @click="sortBy(column.key)">
                                        {{ column.label }}
                                        <Icon
                                            v-if="sortKey === column.key"
                                            name="chevron-right"
                                            class="size-3.5 transition"
                                            :class="sortDescending ? 'rotate-90' : '-rotate-90'"
                                        />
                                    </button>
                                </th>
                                <th class="w-10 px-3 py-2.5"><span class="sr-only">Ouvrir la fiche</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <tr
                                v-for="row in sortedRows"
                                :key="row.employee.id"
                                class="group cursor-pointer transition hover:bg-cream/50"
                                @click="router.visit(route('reports.employee', { employee: row.employee.id, mois: month.value }))"
                            >
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid size-8 place-items-center rounded-xl bg-cream text-[11px] font-semibold">{{ row.employee.initials }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium group-hover:text-brand-dark">{{ row.employee.name }}</span>
                                            <span class="block truncate text-xs text-ink/50">{{ row.employee.department ?? 'Sans département' }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-ink/5">
                                            <div class="h-full rounded-full bg-brand" :style="{ width: `${percentOf(row.summary.workedMinutes, row.summary.scheduledMinutes)}%` }" />
                                        </div>
                                        <span class="tabular-nums">
                                            <span class="font-semibold">{{ formatDuration(row.summary.workedMinutes, '0 h') }}</span>
                                            <span class="text-xs text-ink/45"> / {{ formatDuration(row.summary.scheduledMinutes, '0 h') }}</span>
                                        </span>
                                    </div>
                                    <p v-if="row.summary.overtimeMinutes > 0" class="mt-0.5 text-xs text-brand-dark">+{{ formatDuration(row.summary.overtimeMinutes) }} sup</p>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-full bg-cream px-2 py-0.5 text-xs font-medium tabular-nums">{{ row.summary.workedDays }} j</span>
                                        <span v-if="row.summary.absentDays > 0" class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-800 tabular-nums">
                                            {{ row.summary.absentDays }} abs.
                                        </span>
                                        <span v-if="row.summary.leaveDays > 0" class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800 tabular-nums">
                                            {{ row.summary.leaveDays }} congé
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <span v-if="row.summary.lateCount > 0" class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 tabular-nums">
                                        {{ row.summary.lateCount }} · {{ formatDuration(row.summary.lateMinutes) }}
                                    </span>
                                    <span v-else class="text-xs text-ink/35">Aucun</span>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-14 overflow-hidden rounded-full bg-ink/5">
                                            <div
                                                class="h-full rounded-full"
                                                :class="punctualityTone(row.summary.punctualityRate).bar"
                                                :style="{ width: `${row.summary.punctualityRate ?? 0}%` }"
                                            />
                                        </div>
                                        <span class="text-sm font-semibold tabular-nums" :class="punctualityTone(row.summary.punctualityRate).text">
                                            {{ formatRate(row.summary.punctualityRate) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="w-10 px-3 py-2 text-right">
                                    <Icon name="chevron-right" class="ml-auto size-4 text-ink/30 group-hover:text-brand-dark" />
                                </td>
                            </tr>
                            <tr v-if="sortedRows.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-ink/50">Aucun employé pour ce filtre.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="rows.length > 0" class="flex flex-wrap items-center justify-between gap-2 border-t border-line bg-cream/50 px-4 py-2.5 text-sm">
                    <p class="font-semibold">Total équipe · {{ sortedRows.length }} affiché(s)</p>
                    <p class="tabular-nums text-ink/70">
                        {{ formatDuration(totals.workedMinutes, '0 h') }} /
                        {{ formatDuration(totals.scheduledMinutes, '0 h') }}
                        · {{ totals.workedDays }} j · {{ totals.absentDays }} abs.
                        · {{ formatRate(totals.punctualityRate) }}
                    </p>
                </div>
            </article>

            <div class="grid content-start gap-6">
                <article class="rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-center gap-2">
                        <span class="grid size-8 place-items-center rounded-xl bg-brand/15 text-brand-dark">
                            <Icon name="trending" class="size-4" />
                        </span>
                        <h2 class="font-semibold">Les plus ponctuels</h2>
                    </div>
                    <ol class="mt-4 grid gap-2">
                        <li v-for="(row, index) in mostPunctual" :key="row.employee.id">
                            <Link
                                :href="route('reports.employee', { employee: row.employee.id, mois: month.value })"
                                class="flex items-center gap-3 rounded-2xl bg-cream/70 px-3 py-2.5 text-sm hover:bg-cream"
                            >
                                <span class="w-4 text-center text-xs font-semibold text-ink/40">{{ index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium">{{ row.employee.name }}</span>
                                <span class="font-semibold text-brand-dark tabular-nums">{{ formatRate(row.summary.punctualityRate) }}</span>
                            </Link>
                        </li>
                        <li v-if="mostPunctual.length === 0" class="text-sm text-ink/50">Pas encore de données ce mois-ci.</li>
                    </ol>
                </article>

                <article class="rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-center gap-2">
                        <span class="grid size-8 place-items-center rounded-xl bg-amber-100 text-amber-800">
                            <Icon name="alert" class="size-4" />
                        </span>
                        <h2 class="font-semibold">À surveiller</h2>
                    </div>
                    <ul class="mt-4 grid gap-2">
                        <li v-for="row in toWatch" :key="row.employee.id">
                            <Link
                                :href="route('reports.employee', { employee: row.employee.id, mois: month.value })"
                                class="flex items-center gap-3 rounded-2xl bg-cream/70 px-3 py-2.5 text-sm hover:bg-cream"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium">{{ row.employee.name }}</span>
                                    <span class="block text-xs text-ink/50">
                                        {{ row.summary.absentDays }} absence(s) · {{ row.summary.lateCount }} retard(s), {{ formatDuration(row.summary.lateMinutes, '0 min') }}
                                    </span>
                                </span>
                                <Icon name="chevron-right" class="size-4 text-ink/30" />
                            </Link>
                        </li>
                        <li v-if="toWatch.length === 0" class="rounded-2xl bg-brand/10 px-3 py-3 text-sm text-brand-dark">Aucun retard ni absence ce mois-ci.</li>
                    </ul>
                </article>
            </div>
        </section>
    </div>
</template>
