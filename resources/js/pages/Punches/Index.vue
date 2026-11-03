<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import Pagination from '@/components/Pagination.vue';
import { punchCircleClasses, punchIcon } from '@/lib/format';
import type { Department, Paginated, Punch, PunchTypeValue } from '@/types';

interface Filters {
    du: string;
    au: string;
    employe: number | null;
    departement: number | null;
    action: string | null;
}

const props = defineProps<{
    punches: Paginated<Punch>;
    stats: { total: number; arrivals: number; breaks: number; departures: number; employees: number };
    today: string;
    filters: Filters;
    periodLabel: string;
    employees: { id: number; name: string }[];
    departments: Department[];
    types: PunchTypeValue[];
}>();

const form = reactive<Filters>({ ...props.filters });

const activeFilters = computed(() =>
    Object.fromEntries(Object.entries(form).filter(([, value]) => value !== null && value !== '')),
);

const apply = (): void => {
    router.get(route('punches.index'), activeFilters.value, { preserveState: true, preserveScroll: true });
};

const shiftDate = (date: string, days: number): string => {
    const value = new Date(`${date}T12:00:00`);
    value.setDate(value.getDate() + days);

    return value.toISOString().slice(0, 10);
};

const presets = computed(() => [
    { label: "Aujourd'hui", du: props.today, au: props.today },
    { label: 'Hier', du: shiftDate(props.today, -1), au: shiftDate(props.today, -1) },
    { label: '7 jours', du: shiftDate(props.today, -6), au: props.today },
    { label: 'Ce mois', du: `${props.today.slice(0, 8)}01`, au: props.today },
]);

const isPreset = (preset: { du: string; au: string }): boolean => form.du === preset.du && form.au === preset.au;

const applyPreset = (preset: { du: string; au: string }): void => {
    form.du = preset.du;
    form.au = preset.au;
    apply();
};

const clearFilter = (key: 'employe' | 'departement' | 'action'): void => {
    form[key] = null;
    apply();
};

const activeChips = computed(() => {
    const chips: { key: 'employe' | 'departement' | 'action'; label: string }[] = [];
    const employee = props.employees.find((item) => item.id === props.filters.employe);
    const department = props.departments.find((item) => item.id === props.filters.departement);
    const type = props.types.find((item) => item.value === props.filters.action);

    if (employee) {
        chips.push({ key: 'employe', label: employee.name });
    }

    if (department) {
        chips.push({ key: 'departement', label: department.name });
    }

    if (type) {
        chips.push({ key: 'action', label: type.label });
    }

    return chips;
});

const groups = computed(() => {
    const byDay = new Map<string, { label: string; items: typeof props.punches.data }>();

    for (const punch of props.punches.data) {
        if (!byDay.has(punch.date)) {
            byDay.set(punch.date, { label: punch.dayLabel, items: [] });
        }

        byDay.get(punch.date)!.items.push(punch);
    }

    return [...byDay.entries()].map(([date, group]) => ({ date, ...group }));
});

const actionChip = (type: Punch['type']['value']): string => {
    if (type === 'arrival') {
        return 'bg-brand/15 text-brand-dark';
    }

    if (type === 'departure') {
        return 'bg-stone-100 text-stone-700';
    }

    return 'bg-amber-50 text-amber-800';
};

const statCards = computed(() => [
    { label: 'Pointages', value: props.stats.total, icon: 'history', accent: 'bg-ink text-cream' },
    { label: 'Arrivées', value: props.stats.arrivals, icon: 'login', accent: 'bg-brand/15 text-brand-dark' },
    { label: 'Pauses', value: props.stats.breaks, icon: 'coffee', accent: 'bg-amber-100 text-amber-800' },
    { label: 'Départs', value: props.stats.departures, icon: 'logout', accent: 'bg-stone-200 text-stone-700' },
    { label: 'Employés', value: props.stats.employees, icon: 'users', accent: 'bg-sky-100 text-sky-800' },
]);

const fieldClasses = 'h-10 w-full rounded-xl border border-line bg-white px-3 text-sm text-ink outline-none focus:border-brand';
</script>

<template>
    <Head title="Pointages" />

    <div class="grid gap-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-brand-dark">Historique</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">Pointages du personnel</h1>
                <p class="mt-1 text-sm text-ink/55">Période {{ periodLabel }}</p>
            </div>
            <a
                :href="route('punches.export', activeFilters)"
                class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-5 text-sm font-medium text-cream hover:bg-ink/90"
            >
                <Icon name="sheet" class="size-4" />
                Exporter en Excel
            </a>
        </header>

        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <article v-for="card in statCards" :key="card.label" class="flex items-center gap-3 rounded-2xl border border-line bg-paper p-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-xl" :class="card.accent">
                    <Icon :name="card.icon" class="size-5" />
                </span>
                <div>
                    <p class="text-2xl font-semibold tracking-tight tabular-nums">{{ card.value }}</p>
                    <p class="text-xs text-ink/55">{{ card.label }}</p>
                </div>
            </article>
        </section>

        <section class="grid gap-4 rounded-3xl border border-line bg-paper p-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="mr-1 text-xs font-medium tracking-wide text-ink/45 uppercase">Période</span>
                <button
                    v-for="preset in presets"
                    :key="preset.label"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm transition"
                    :class="isPreset(preset) ? 'bg-ink font-medium text-cream' : 'bg-cream text-ink/65 hover:text-ink'"
                    @click="applyPreset(preset)"
                >
                    {{ preset.label }}
                </button>
            </div>

            <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]" @submit.prevent="apply">
                <label class="grid gap-1 text-xs font-medium text-ink/55">
                    Du
                    <input v-model="form.du" type="date" :max="form.au" :class="fieldClasses">
                </label>
                <label class="grid gap-1 text-xs font-medium text-ink/55">
                    Au
                    <input v-model="form.au" type="date" :min="form.du" :max="today" :class="fieldClasses">
                </label>
                <label class="grid gap-1 text-xs font-medium text-ink/55">
                    Employé
                    <select v-model="form.employe" :class="fieldClasses">
                        <option :value="null">Tous les employés</option>
                        <option v-for="employee in employees" :key="employee.id" :value="employee.id">{{ employee.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-medium text-ink/55">
                    Département
                    <select v-model="form.departement" :class="fieldClasses">
                        <option :value="null">Tous les départements</option>
                        <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                    </select>
                </label>
                <label class="grid gap-1 text-xs font-medium text-ink/55">
                    Action
                    <select v-model="form.action" :class="fieldClasses">
                        <option :value="null">Toutes les actions</option>
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand px-5 text-sm font-semibold text-ink hover:bg-brand/90">
                        <Icon name="filter" class="size-4" />
                        Filtrer
                    </button>
                </div>
            </form>

            <div v-if="activeChips.length" class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                <span class="text-xs text-ink/45">Filtres actifs :</span>
                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full bg-brand/15 px-3 py-1 text-xs font-medium text-brand-dark hover:bg-brand/25"
                    @click="clearFilter(chip.key)"
                >
                    {{ chip.label }}
                    <Icon name="x" class="size-3.5" />
                </button>
                <Link :href="route('punches.index')" class="text-xs text-ink/50 underline-offset-2 hover:underline">Tout effacer</Link>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-line bg-paper">
            <template v-for="group in groups" :key="group.date">
                <div class="sticky top-0 z-[1] flex items-center justify-between border-b border-line bg-cream/90 px-5 py-2.5 backdrop-blur">
                    <h2 class="text-sm font-semibold">{{ group.label }}</h2>
                    <span class="text-xs text-ink/50">{{ group.items.length }} pointage(s)</span>
                </div>
                <ul class="divide-y divide-line">
                    <li v-for="punch in group.items" :key="punch.id" class="grid grid-cols-[4rem_minmax(0,1fr)_auto] items-center gap-4 px-5 py-3 transition hover:bg-cream/40 sm:grid-cols-[4.5rem_minmax(0,1fr)_11rem_7rem]">
                        <span class="text-base font-semibold tabular-nums">{{ punch.time }}</span>

                        <Link
                            v-if="punch.employee"
                            :href="route('reports.employee', { employee: punch.employee.id, mois: punch.month })"
                            class="group flex min-w-0 items-center gap-3"
                        >
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-cream text-xs font-semibold">{{ punch.employee.initials }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium group-hover:text-brand-dark">{{ punch.employee.name }}</span>
                                <span class="block truncate text-xs text-ink/50">{{ punch.employee.department ?? 'Sans département' }}</span>
                            </span>
                        </Link>

                        <span class="flex flex-wrap items-center justify-end gap-2 sm:justify-start">
                            <span class="inline-flex items-center gap-1.5 rounded-full py-1 pr-3 pl-1 text-xs font-medium" :class="actionChip(punch.type.value)">
                                <span class="grid size-5 place-items-center rounded-full" :class="punchCircleClasses(punch.type.value)">
                                    <Icon :name="punchIcon(punch.type.value)" class="size-3" />
                                </span>
                                {{ punch.type.label }}
                            </span>
                            <span v-if="punch.lateMinutes" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                                +{{ punch.lateMinutes }} min
                            </span>
                            <span
                                v-if="punch.isOffline"
                                class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800"
                                :title="`Pointé sans réseau, envoyé le ${punch.syncedAt}`"
                            >
                                <Icon name="wifi-off" class="size-3" />
                                Hors ligne
                            </span>
                            <span v-if="punch.isManual" class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800" title="Ajouté par le gérant">
                                Saisie gérant
                            </span>
                            <span v-else-if="punch.isCorrected" class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800" title="Modifié par le gérant">
                                Corrigé
                            </span>
                        </span>

                        <span class="hidden items-center gap-1.5 text-xs text-ink/50 sm:flex">
                            <Icon name="qr" class="size-3.5" />
                            {{ punch.kiosk }}
                        </span>
                    </li>
                </ul>
            </template>

            <div v-if="punches.data.length === 0" class="grid place-items-center gap-3 px-5 py-16 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-cream text-ink/40">
                    <Icon name="history" class="size-7" />
                </span>
                <p class="font-medium">Aucun pointage sur cette période</p>
                <p class="text-sm text-ink/50">Élargissez les dates ou retirez un filtre.</p>
            </div>
        </section>

        <div v-if="punches.total > 0" class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink/50">{{ punches.from }} – {{ punches.to }} sur {{ punches.total }} pointages</p>
            <Pagination :links="punches.links" />
        </div>
    </div>
</template>
