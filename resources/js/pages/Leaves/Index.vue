<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { LeaveRequest, Paginated } from '@/types';

const props = defineProps<{
    leaveRequests: Paginated<LeaveRequest>;
    status: string | null;
    pendingCount: number;
    stats: { pending: number; approved: number; rejected: number; onLeaveToday: number };
    upcoming: LeaveRequest[];
}>();

const tabs = computed(() => [
    { value: null, label: 'Toutes', count: props.stats.pending + props.stats.approved + props.stats.rejected },
    { value: 'pending', label: 'En attente', count: props.stats.pending },
    { value: 'approved', label: 'Acceptées', count: props.stats.approved },
    { value: 'rejected', label: 'Refusées', count: props.stats.rejected },
]);

const statCards = computed(() => [
    { label: 'À traiter', value: props.stats.pending, icon: 'alert', accent: 'bg-amber-100 text-amber-800' },
    { label: 'En congé aujourd’hui', value: props.stats.onLeaveToday, icon: 'plane', accent: 'bg-sky-100 text-sky-800' },
    { label: 'Acceptées', value: props.stats.approved, icon: 'check', accent: 'bg-brand/15 text-brand-dark' },
    { label: 'Refusées', value: props.stats.rejected, icon: 'x', accent: 'bg-rose-100 text-rose-800' },
]);

const notes = reactive<Record<number, string>>({});
const processing = ref<number | null>(null);

const review = (leave: LeaveRequest, decision: 'approved' | 'rejected'): void => {
    processing.value = leave.id;

    router.patch(
        route('leaves.update', leave.id),
        { status: decision, review_note: notes[leave.id] ?? '' },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = null;
            },
        },
    );
};

const stripe = (leave: LeaveRequest): string => {
    switch (leave.status.value) {
        case 'pending':
            return 'bg-amber-400';
        case 'approved':
            return 'bg-brand';
        default:
            return 'bg-rose-400';
    }
};

const typeIcon = (type: string): string => {
    switch (type) {
        case 'sick':
            return 'alert';
        case 'permission':
            return 'clock';
        case 'paid_leave':
            return 'plane';
        default:
            return 'calendar';
    }
};
</script>

<template>
    <Head title="Congés" />

    <div class="grid gap-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-brand-dark">Congés et absences</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                    {{ pendingCount > 0 ? `${pendingCount} demande(s) attendent votre décision` : 'Toutes les demandes sont traitées' }}
                </h1>
                <p class="mt-1 text-sm text-ink/55">Un congé accepté remplace automatiquement l’absence dans les rapports.</p>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <article v-for="card in statCards" :key="card.label" class="flex items-center gap-3 rounded-2xl border border-line bg-paper p-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-xl" :class="card.accent">
                    <Icon :name="card.icon" />
                </span>
                <div>
                    <p class="text-2xl font-semibold tracking-tight tabular-nums">{{ card.value }}</p>
                    <p class="text-xs text-ink/55">{{ card.label }}</p>
                </div>
            </article>
        </section>

        <section class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="grid content-start gap-4">
                <nav class="inline-flex w-fit flex-wrap gap-1 rounded-full border border-line bg-paper p-1 text-sm">
                    <Link
                        v-for="tab in tabs"
                        :key="tab.label"
                        :href="route('leaves.index', tab.value ? { statut: tab.value } : {})"
                        preserve-scroll
                        class="inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 transition"
                        :class="status === tab.value ? 'bg-ink font-medium text-cream' : 'text-ink/60 hover:text-ink'"
                    >
                        {{ tab.label }}
                        <span class="rounded-full px-1.5 text-xs tabular-nums" :class="status === tab.value ? 'bg-white/15' : 'bg-ink/5'">{{ tab.count }}</span>
                    </Link>
                </nav>

                <article
                    v-for="leave in leaveRequests.data"
                    :key="leave.id"
                    class="relative overflow-hidden rounded-3xl border border-line bg-paper"
                >
                    <span class="absolute inset-y-0 left-0 w-1.5" :class="stripe(leave)" />

                    <div class="grid gap-5 p-5 pl-7 lg:grid-cols-[5rem_minmax(0,1fr)_auto] lg:items-center">
                        <div class="hidden flex-col items-center justify-center rounded-2xl bg-cream py-3 text-center lg:flex">
                            <span class="text-2xl leading-none font-semibold tabular-nums">{{ leave.startDay }}</span>
                            <span class="mt-1 text-xs tracking-wide text-ink/55 uppercase">{{ leave.startMonth }}</span>
                        </div>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="grid size-9 place-items-center rounded-xl bg-cream text-xs font-semibold lg:hidden">{{ leave.employee?.initials }}</span>
                                <p class="text-base font-semibold">{{ leave.employee?.name }}</p>
                                <StatusBadge :tone="leave.status.tone" :label="leave.status.label" />
                                <span class="rounded-full bg-ink/5 px-2.5 py-0.5 text-xs text-ink/60">{{ leave.timing }}</span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink/70">
                                <span class="inline-flex items-center gap-1.5 font-medium text-ink">
                                    <Icon :name="typeIcon(leave.type.value)" class="size-4 text-ink/45" />
                                    {{ leave.type.label }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <Icon name="calendar" class="size-4 text-ink/45" />
                                    {{ leave.startsLabel }} → {{ leave.endsLabel }}
                                </span>
                                <span class="rounded-full bg-cream px-2 py-0.5 text-xs font-medium tabular-nums">{{ leave.dayCount }} jour(s)</span>
                            </div>

                            <p v-if="leave.reason" class="mt-3 rounded-2xl bg-cream/70 px-4 py-2.5 text-sm text-ink/70 italic">« {{ leave.reason }} »</p>

                            <p v-if="leave.status.value !== 'pending'" class="mt-3 text-xs text-ink/45">
                                {{ leave.status.label }} par {{ leave.reviewer ?? 'le gérant' }} le {{ leave.reviewedAt }}
                                <template v-if="leave.reviewNote"> · « {{ leave.reviewNote }} »</template>
                            </p>
                            <p v-else-if="leave.submittedAt" class="mt-3 text-xs text-ink/45">Demandé le {{ leave.submittedAt }}</p>
                        </div>

                        <div v-if="leave.status.value === 'pending'" class="grid gap-2 lg:w-60">
                            <input
                                v-model="notes[leave.id]"
                                placeholder="Note pour l’employé (facultatif)"
                                class="h-10 w-full rounded-xl border border-line bg-white px-3 text-sm outline-none focus:border-brand"
                            >
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    :disabled="processing === leave.id"
                                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 text-sm font-medium text-rose-800 transition hover:bg-rose-100 disabled:opacity-60"
                                    @click="review(leave, 'rejected')"
                                >
                                    <Icon name="x" class="size-4" />
                                    Refuser
                                </button>
                                <button
                                    type="button"
                                    :disabled="processing === leave.id"
                                    class="inline-flex h-10 items-center justify-center gap-1.5 rounded-xl bg-brand text-sm font-semibold text-ink transition hover:bg-brand/90 disabled:opacity-60"
                                    @click="review(leave, 'approved')"
                                >
                                    <Icon name="check" class="size-4" />
                                    Accepter
                                </button>
                            </div>
                        </div>
                    </div>
                </article>

                <div v-if="leaveRequests.data.length === 0" class="grid place-items-center gap-3 rounded-3xl border border-dashed border-line bg-paper px-5 py-16 text-center">
                    <span class="grid size-14 place-items-center rounded-2xl bg-cream text-ink/40">
                        <Icon name="plane" class="size-7" />
                    </span>
                    <p class="font-medium">Aucune demande ici</p>
                    <p class="text-sm text-ink/50">Les demandes envoyées par le personnel apparaîtront dans cette liste.</p>
                </div>

                <Pagination :links="leaveRequests.links" />
            </div>

            <aside class="grid content-start gap-6">
                <article class="rounded-3xl border border-line bg-paper p-5">
                    <div class="flex items-center gap-2">
                        <span class="grid size-8 place-items-center rounded-xl bg-sky-100 text-sky-800">
                            <Icon name="calendar" class="size-4" />
                        </span>
                        <h2 class="font-semibold">Absences prévues</h2>
                    </div>
                    <ol class="relative mt-5 grid gap-4 before:absolute before:top-2 before:bottom-2 before:left-[1.4rem] before:w-px before:bg-line">
                        <li v-for="leave in upcoming" :key="leave.id" class="relative flex items-center gap-3 text-sm">
                            <span class="relative z-10 grid w-11 shrink-0 place-items-center rounded-xl bg-paper py-1 text-center ring-1 ring-line">
                                <span class="text-sm leading-tight font-semibold tabular-nums">{{ leave.startDay }}</span>
                                <span class="text-[10px] text-ink/50 uppercase">{{ leave.startMonth }}</span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ leave.employee?.name }}</span>
                                <span class="block text-xs text-ink/50">{{ leave.type.label }} · {{ leave.dayCount }} jour(s)</span>
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="leave.timing === 'En cours' ? 'bg-sky-100 text-sky-800' : 'bg-ink/5 text-ink/55'">
                                {{ leave.timing }}
                            </span>
                        </li>
                        <li v-if="upcoming.length === 0" class="relative pl-14 text-sm text-ink/50">Aucune absence prévue.</li>
                    </ol>
                </article>

                <article class="rounded-3xl bg-ink p-5 text-cream">
                    <h2 class="font-semibold">Comment ça marche</h2>
                    <ul class="mt-4 grid gap-3 text-sm text-cream/70">
                        <li class="flex gap-3">
                            <Icon name="smartphone" class="mt-0.5 size-4 text-brand" />
                            Le personnel fait sa demande depuis son téléphone, onglet « Congés ».
                        </li>
                        <li class="flex gap-3">
                            <Icon name="check-circle" class="mt-0.5 size-4 text-brand" />
                            Vous acceptez ou refusez, avec une note si besoin.
                        </li>
                        <li class="flex gap-3">
                            <Icon name="chart" class="mt-0.5 size-4 text-brand" />
                            Les jours acceptés ne comptent plus comme absences.
                        </li>
                    </ul>
                </article>
            </aside>
        </section>
    </div>
</template>
