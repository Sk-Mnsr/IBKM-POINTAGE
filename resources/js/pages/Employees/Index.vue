<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import { formatDuration } from '@/lib/format';
import { scheduleSummary, weeklyMinutes } from '@/lib/schedule';
import type { Department, Employee } from '@/types';

const props = defineProps<{ employees: Employee[]; departments: Department[] }>();

const search = ref('');
const departmentFilter = ref<number | null>(null);
const statusFilter = ref<'all' | 'active' | 'inactive'>('active');

const activeCount = computed(() => props.employees.filter((employee) => employee.isActive).length);

const visibleEmployees = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.employees.filter((employee) => {
        if (statusFilter.value === 'active' && !employee.isActive) {
            return false;
        }

        if (statusFilter.value === 'inactive' && employee.isActive) {
            return false;
        }

        if (departmentFilter.value !== null && employee.departmentId !== departmentFilter.value) {
            return false;
        }

        return !term || `${employee.name} ${employee.phone ?? ''} ${employee.email ?? ''}`.toLowerCase().includes(term);
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

const avatarClasses = (employee: Employee): string => avatarPalette[(employee.departmentId ?? 0) % avatarPalette.length];

const departmentCount = (id: number): number => props.employees.filter((employee) => employee.departmentId === id && employee.isActive).length;

const formatPhone = (phone: string | null): string => (phone ?? '').replace(/(\d{2})(\d{3})(\d{2})(\d{2})/, '$1 $2 $3 $4');
</script>

<template>
    <Head title="Employés" />

    <div class="grid gap-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-brand-dark">Équipe</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ activeCount }} employé(s) actif(s)</h1>
                <p class="mt-1 text-sm text-ink/55">Comptes, départements et horaires théoriques du personnel.</p>
            </div>
            <Link :href="route('employees.create')" class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-5 text-sm font-medium text-cream hover:bg-ink/90">
                <Icon name="plus" class="size-4" />
                Nouvel employé
            </Link>
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button
                v-for="department in departments"
                :key="department.id"
                type="button"
                class="flex items-center gap-3 rounded-2xl border p-4 text-left transition"
                :class="departmentFilter === department.id ? 'border-ink bg-ink text-cream' : 'border-line bg-paper hover:border-ink/30'"
                @click="departmentFilter = departmentFilter === department.id ? null : department.id"
            >
                <span
                    class="grid size-10 shrink-0 place-items-center rounded-xl"
                    :class="departmentFilter === department.id ? 'bg-white/10 text-brand' : 'bg-brand/15 text-brand-dark'"
                >
                    <Icon name="building" />
                </span>
                <span>
                    <span class="block text-2xl font-semibold tabular-nums">{{ departmentCount(department.id) }}</span>
                    <span class="block text-xs" :class="departmentFilter === department.id ? 'text-cream/60' : 'text-ink/55'">{{ department.name }}</span>
                </span>
            </button>
        </section>

        <section class="overflow-hidden rounded-3xl border border-line bg-paper">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div class="flex items-center gap-1 rounded-full bg-cream p-1 text-sm">
                    <button
                        v-for="option in [
                            { key: 'active', label: 'Actifs' },
                            { key: 'inactive', label: 'Désactivés' },
                            { key: 'all', label: 'Tous' },
                        ] as const"
                        :key="option.key"
                        type="button"
                        class="rounded-full px-3.5 py-1.5 transition"
                        :class="statusFilter === option.key ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                        @click="statusFilter = option.key"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Nom, téléphone ou e-mail…"
                    aria-label="Rechercher un employé"
                    class="h-10 w-64 rounded-full border border-line bg-cream/60 px-4 text-sm outline-none focus:border-brand focus:bg-white"
                >
            </div>

            <ul class="divide-y divide-line">
                <li
                    v-for="employee in visibleEmployees"
                    :key="employee.id"
                    class="group grid cursor-pointer grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-5 py-4 transition hover:bg-cream/40 lg:grid-cols-[minmax(0,1.3fr)_9rem_minmax(0,1.2fr)_8rem_auto]"
                    @click="router.visit(route('employees.edit', employee.id))"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl text-sm font-semibold" :class="avatarClasses(employee)">{{ employee.initials }}</span>
                        <div class="min-w-0">
                            <p class="truncate font-medium group-hover:text-brand-dark">{{ employee.name }}</p>
                            <p class="truncate text-xs text-ink/50">{{ employee.department ?? 'Sans département' }} · {{ employee.email ?? 'Sans e-mail' }}</p>
                        </div>
                    </div>

                    <p class="hidden text-sm tabular-nums text-ink/70 lg:block">{{ formatPhone(employee.phone) }}</p>

                    <div class="hidden min-w-0 lg:block">
                        <p class="truncate text-sm">{{ scheduleSummary(employee.schedules ?? []) }}</p>
                        <p class="text-xs text-ink/50">{{ formatDuration(weeklyMinutes(employee.schedules ?? []), '0 h') }} / semaine</p>
                    </div>

                    <span
                        class="hidden w-fit items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium lg:inline-flex"
                        :class="employee.isActive ? 'bg-brand/15 text-brand-dark' : 'bg-rose-100 text-rose-800'"
                    >
                        <span class="size-1.5 rounded-full" :class="employee.isActive ? 'bg-brand' : 'bg-rose-500'" />
                        {{ employee.isActive ? 'Actif' : 'Désactivé' }}
                    </span>

                    <div class="flex items-center gap-1" @click.stop>
                        <Link
                            :href="route('reports.employee', employee.id)"
                            class="grid size-9 place-items-center rounded-full text-ink/50 hover:bg-cream hover:text-brand-dark"
                            :aria-label="`Fiche de présence de ${employee.name}`"
                        >
                            <Icon name="chart" class="size-4" />
                        </Link>
                        <Link
                            :href="route('employees.edit', employee.id)"
                            class="grid size-9 place-items-center rounded-full text-ink/50 hover:bg-cream hover:text-ink"
                            :aria-label="`Modifier ${employee.name}`"
                        >
                            <Icon name="pencil" class="size-4" />
                        </Link>
                    </div>
                </li>
            </ul>

            <div v-if="visibleEmployees.length === 0" class="grid place-items-center gap-3 px-5 py-16 text-center">
                <span class="grid size-14 place-items-center rounded-2xl bg-cream text-ink/40">
                    <Icon name="users" class="size-7" />
                </span>
                <p class="font-medium">{{ employees.length === 0 ? 'Aucun employé pour le moment' : 'Aucun employé ne correspond' }}</p>
                <Link v-if="employees.length === 0" :href="route('employees.create')" class="text-sm font-medium text-brand-dark">Ajouter le premier employé</Link>
                <p v-else class="text-sm text-ink/50">Modifiez la recherche ou les filtres.</p>
            </div>
        </section>
    </div>
</template>
