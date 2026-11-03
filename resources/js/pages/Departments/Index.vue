<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Alert from '@/components/Alert.vue';
import Icon from '@/components/Icon.vue';
import type { Department } from '@/types';

defineProps<{ departments: Department[] }>();

const page = usePage();

const destroy = (department: Department): void => {
    if (!window.confirm(`Supprimer le département « ${department.name} » ?`)) {
        return;
    }

    router.delete(route('departments.destroy', department.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Départements" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Départements</h1>
            <p class="mt-1 text-sm text-ink/60">Regroupez le personnel par métier pour filtrer les rapports.</p>
        </div>
        <Link :href="route('departments.create')" class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-4 text-sm font-medium text-cream">
            <Icon name="plus" class="size-4" />
            Nouveau département
        </Link>
    </div>

    <Alert v-if="page.props.errors.name" tone="error" class="mt-6">{{ page.props.errors.name }}</Alert>

    <ul class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <li v-for="department in departments" :key="department.id" class="flex flex-col justify-between gap-6 rounded-2xl border border-line bg-paper p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold">{{ department.name }}</p>
                    <p class="text-sm text-ink/60">{{ department.employeeCount ?? 0 }} employé(s)</p>
                </div>
                <span class="grid size-10 place-items-center rounded-xl bg-brand/15 text-brand-dark">
                    <Icon name="building" />
                </span>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <Link :href="route('reports.index', { departement: department.id })" class="inline-flex items-center gap-1 text-brand-dark">
                    <Icon name="chart" class="size-4" />
                    Rapport
                </Link>
                <Link :href="route('departments.edit', department.id)" class="inline-flex items-center gap-1 text-ink/70">
                    <Icon name="pencil" class="size-4" />
                    Modifier
                </Link>
                <button type="button" class="ml-auto text-ink/50 hover:text-rose-700" @click="destroy(department)">Supprimer</button>
            </div>
        </li>
    </ul>
</template>
