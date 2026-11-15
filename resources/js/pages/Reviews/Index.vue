<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import Pagination from '@/components/Pagination.vue';
import type { Paginated } from '@/types';

interface RatingBadge {
    value: string;
    label: string;
    tone: string;
}

interface ReviewRow {
    id: number;
    rating: RatingBadge;
    comment: string | null;
    kiosk: string | null;
    shop: string | null;
    at: string | null;
    dayLabel: string;
}

interface Filters {
    du: string;
    au: string;
    borne: number | null;
}

const props = defineProps<{
    reviews: Paginated<ReviewRow>;
    stats: {
        total: number;
        verySatisfied: number;
        satisfied: number;
        unsatisfied: number;
        satisfactionRate: number | null;
    };
    byKiosk: { kioskId: number; name: string; total: number }[];
    filters: Filters;
    kiosks: { id: number; name: string }[];
    showShop: boolean;
    periodLabel: string;
}>();

const form = reactive<Filters>({ ...props.filters });

const activeFilters = computed(() =>
    Object.fromEntries(Object.entries(form).filter(([, value]) => value !== null && value !== '')),
);

const apply = (): void => {
    router.get(route('reviews.index'), activeFilters.value, { preserveState: true, preserveScroll: true });
};

const shiftDate = (date: string, days: number): string => {
    const value = new Date(`${date}T12:00:00`);
    value.setDate(value.getDate() + days);

    return value.toISOString().slice(0, 10);
};

const today = computed(() => new Date().toISOString().slice(0, 10));

const presets = computed(() => [
    { label: "Aujourd'hui", du: today.value, au: today.value },
    { label: '7 jours', du: shiftDate(today.value, -6), au: today.value },
    { label: '30 jours', du: shiftDate(today.value, -29), au: today.value },
    { label: 'Ce mois', du: `${today.value.slice(0, 8)}01`, au: today.value },
]);

const isPreset = (preset: { du: string; au: string }): boolean => form.du === preset.du && form.au === preset.au;

const applyPreset = (preset: { du: string; au: string }): void => {
    form.du = preset.du;
    form.au = preset.au;
    apply();
};

const clearBorne = (): void => {
    form.borne = null;
    apply();
};

const share = (count: number): number => {
    if (props.stats.total === 0) {
        return 0;
    }

    return Math.round((count / props.stats.total) * 100);
};

const moodBars = computed(() => [
    {
        value: 'very_satisfied',
        label: 'Très satisfaits',
        count: props.stats.verySatisfied,
        percent: share(props.stats.verySatisfied),
        bar: 'bg-brand',
        soft: 'bg-brand/15 text-brand-dark',
        face: '#92bc35',
    },
    {
        value: 'satisfied',
        label: 'Satisfaits',
        count: props.stats.satisfied,
        percent: share(props.stats.satisfied),
        bar: 'bg-amber-400',
        soft: 'bg-amber-100 text-amber-900',
        face: '#fbbf24',
    },
    {
        value: 'unsatisfied',
        label: 'Insatisfaits',
        count: props.stats.unsatisfied,
        percent: share(props.stats.unsatisfied),
        bar: 'bg-rose-400',
        soft: 'bg-rose-100 text-rose-900',
        face: '#fda4af',
    },
]);

const maxKioskTotal = computed(() => Math.max(...props.byKiosk.map((row) => row.total), 1));

const groups = computed(() => {
    const byDay = new Map<string, { label: string; items: ReviewRow[] }>();

    for (const review of props.reviews.data) {
        const key = review.dayLabel || review.at || String(review.id);

        if (!byDay.has(key)) {
            byDay.set(key, { label: review.dayLabel || 'Avis', items: [] });
        }

        byDay.get(key)!.items.push(review);
    }

    return [...byDay.values()];
});

const faceFill = (value: string): string => {
    switch (value) {
        case 'very_satisfied':
            return '#92bc35';
        case 'satisfied':
            return '#fbbf24';
        default:
            return '#fda4af';
    }
};

const stripe = (value: string): string => {
    switch (value) {
        case 'very_satisfied':
            return 'bg-brand';
        case 'satisfied':
            return 'bg-amber-400';
        default:
            return 'bg-rose-400';
    }
};

const timeOnly = (at: string | null): string => {
    if (!at) {
        return '';
    }

    const parts = at.split(' ');

    return parts[1] ?? at;
};
</script>

<template>
    <Head title="Avis clients" />

    <div class="grid gap-6">
        <section class="relative overflow-hidden rounded-[2rem] bg-ink px-6 py-7 text-cream sm:px-8">
            <div class="pointer-events-none absolute -top-24 -right-16 size-64 rounded-full bg-brand/25 blur-3xl" />
            <div class="pointer-events-none absolute -bottom-28 left-10 size-56 rounded-full bg-brand/10 blur-3xl" />

            <div class="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                <div>
                    <p class="text-sm font-semibold tracking-[0.22em] text-brand uppercase">Satisfaction client</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Ce que disent vos clients</h1>
                    <p class="mt-2 max-w-xl text-sm text-cream/55">
                        Avis laissés sur les bornes · {{ periodLabel }}
                    </p>

                    <div class="mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="preset in presets"
                            :key="preset.label"
                            type="button"
                            class="rounded-full px-3.5 py-1.5 text-sm transition"
                            :class="isPreset(preset) ? 'bg-brand font-medium text-ink' : 'bg-white/8 text-cream/70 hover:bg-white/12 hover:text-cream'"
                            @click="applyPreset(preset)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-5 rounded-[1.75rem] border border-white/10 bg-white/5 px-6 py-5 backdrop-blur">
                    <div class="relative grid size-28 place-items-center">
                        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="8" class="text-white/10" />
                            <circle
                                cx="50"
                                cy="50"
                                r="42"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="8"
                                stroke-linecap="round"
                                pathLength="100"
                                stroke-dasharray="100"
                                :stroke-dashoffset="100 - (stats.satisfactionRate ?? 0)"
                                class="text-brand transition-[stroke-dashoffset] duration-700"
                            />
                        </svg>
                        <div class="text-center">
                            <p class="text-3xl font-semibold tabular-nums">
                                {{ stats.satisfactionRate === null ? '—' : `${stats.satisfactionRate}%` }}
                            </p>
                            <p class="text-[10px] tracking-wide text-cream/45 uppercase">satisfaits</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-4xl font-semibold tracking-tight tabular-nums">{{ stats.total }}</p>
                        <p class="text-sm text-cream/55">avis sur la période</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="grid gap-3 rounded-[1.75rem] border border-line bg-paper p-5">
                <p class="text-sm font-medium text-ink/55">Répartition des notes</p>
                <div class="mt-1 flex h-3 overflow-hidden rounded-full bg-stone-100">
                    <span
                        v-for="mood in moodBars"
                        :key="mood.value"
                        class="h-full transition-all"
                        :class="mood.bar"
                        :style="{ width: `${mood.percent}%` }"
                        :title="`${mood.label} · ${mood.percent}%`"
                    />
                </div>
                <div class="mt-2 grid gap-3 sm:grid-cols-3">
                    <article v-for="mood in moodBars" :key="mood.value" class="flex items-center gap-3 rounded-2xl px-3 py-3" :class="mood.soft">
                        <span class="grid size-11 place-items-center rounded-full" :style="{ backgroundColor: mood.face }">
                            <svg class="size-7" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                                <template v-if="mood.value === 'very_satisfied'">
                                    <circle cx="42" cy="48" r="7" fill="#111" />
                                    <circle cx="78" cy="48" r="7" fill="#111" />
                                    <path d="M36 68c8 18 40 18 48 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                </template>
                                <template v-else-if="mood.value === 'satisfied'">
                                    <circle cx="42" cy="50" r="7" fill="#111" />
                                    <circle cx="78" cy="50" r="7" fill="#111" />
                                    <path d="M40 76h40" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                </template>
                                <template v-else>
                                    <path d="M34 44c4 8 12 8 16 0M70 44c4 8 12 8 16 0" stroke="#111" stroke-width="7" stroke-linecap="round" />
                                    <path d="M40 84c10-14 30-14 40 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                </template>
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-xl font-semibold tabular-nums">{{ mood.count }}</p>
                            <p class="truncate text-xs opacity-70">{{ mood.label }} · {{ mood.percent }}%</p>
                        </div>
                    </article>
                </div>
            </div>

            <div class="grid gap-3 rounded-[1.75rem] border border-line bg-paper p-5">
                <p class="text-sm font-medium text-ink/55">Par borne</p>
                <div v-if="byKiosk.length === 0" class="grid place-items-center rounded-2xl bg-cream/60 px-4 py-10 text-center text-sm text-ink/45">
                    Aucune borne n’a encore reçu d’avis
                </div>
                <div v-else class="grid gap-3">
                    <article v-for="row in byKiosk" :key="row.kioskId" class="grid gap-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="font-medium">{{ row.name }}</span>
                            <span class="tabular-nums text-ink/55">{{ row.total }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-stone-100">
                            <div class="h-full rounded-full bg-ink transition-all" :style="{ width: `${(row.total / maxKioskTotal) * 100}%` }" />
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="rounded-[1.75rem] border border-line bg-paper p-4 sm:p-5">
            <form class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto]" @submit.prevent="apply">
                <label class="grid gap-1 text-sm">
                    <span class="text-ink/55">Du</span>
                    <input v-model="form.du" type="date" class="rounded-xl border border-line bg-white px-3 py-2.5" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="text-ink/55">Au</span>
                    <input v-model="form.au" type="date" class="rounded-xl border border-line bg-white px-3 py-2.5" />
                </label>
                <label class="grid gap-1 text-sm">
                    <span class="text-ink/55">Borne</span>
                    <select v-model="form.borne" class="rounded-xl border border-line bg-white px-3 py-2.5">
                        <option :value="null">Toutes</option>
                        <option v-for="kiosk in kiosks" :key="kiosk.id" :value="kiosk.id">{{ kiosk.name }}</option>
                    </select>
                </label>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-ink px-4 py-2.5 text-sm font-medium text-cream">
                        <Icon name="filter" class="size-4" />
                        Filtrer
                    </button>
                </div>
            </form>

            <button
                v-if="filters.borne"
                type="button"
                class="mt-3 w-fit rounded-full bg-stone-100 px-3 py-1 text-xs text-ink/70 hover:bg-stone-200"
                @click="clearBorne"
            >
                Borne : {{ kiosks.find((item) => item.id === filters.borne)?.name }} · effacer
            </button>
        </section>

        <section v-if="reviews.data.length === 0" class="rounded-[1.75rem] border border-dashed border-line bg-paper px-6 py-16 text-center">
            <span class="mx-auto grid size-16 place-items-center rounded-full bg-brand/15 text-brand-dark">
                <Icon name="star" class="size-8" />
            </span>
            <p class="mt-4 text-lg font-semibold">Aucun avis sur cette période</p>
            <p class="mt-1 text-sm text-ink/55">Les clients notent depuis le QR de la borne ou le bouton « Avis client ».</p>
        </section>

        <section v-else class="grid gap-6">
            <div v-for="group in groups" :key="group.label" class="grid gap-3">
                <h2 class="text-sm font-semibold tracking-wide text-ink/45 uppercase">{{ group.label }}</h2>

                <article
                    v-for="review in group.items"
                    :key="review.id"
                    class="relative overflow-hidden rounded-[1.5rem] border border-line bg-paper"
                >
                    <span class="absolute inset-y-0 left-0 w-1.5" :class="stripe(review.rating.value)" />

                    <div class="grid gap-4 p-4 pl-6 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-center sm:p-5 sm:pl-7">
                        <span class="grid size-14 place-items-center rounded-full" :style="{ backgroundColor: faceFill(review.rating.value) }">
                            <svg class="size-9" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                                <template v-if="review.rating.value === 'very_satisfied'">
                                    <circle cx="42" cy="48" r="7" fill="#111" />
                                    <circle cx="78" cy="48" r="7" fill="#111" />
                                    <path d="M36 68c8 18 40 18 48 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                </template>
                                <template v-else-if="review.rating.value === 'satisfied'">
                                    <circle cx="42" cy="50" r="7" fill="#111" />
                                    <circle cx="78" cy="50" r="7" fill="#111" />
                                    <path d="M40 76h40" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                </template>
                                <template v-else>
                                    <path d="M34 44c4 8 12 8 16 0M70 44c4 8 12 8 16 0" stroke="#111" stroke-width="7" stroke-linecap="round" />
                                    <path d="M40 84c10-14 30-14 40 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                </template>
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ review.rating.label }}</p>
                                <span class="rounded-full bg-ink/5 px-2.5 py-0.5 text-xs text-ink/55">
                                    {{ review.kiosk }}
                                    <template v-if="showShop && review.shop"> · {{ review.shop }}</template>
                                </span>
                            </div>
                            <p v-if="review.comment" class="mt-2 rounded-2xl bg-cream/80 px-4 py-2.5 text-sm leading-relaxed text-ink/80 italic">
                                « {{ review.comment }} »
                            </p>
                            <p v-else class="mt-2 text-sm text-ink/35">Sans commentaire</p>
                        </div>

                        <p class="text-sm tabular-nums text-ink/45 sm:text-right">{{ timeOnly(review.at) || review.at }}</p>
                    </div>
                </article>
            </div>

            <Pagination :links="reviews.links" />
        </section>
    </div>
</template>
