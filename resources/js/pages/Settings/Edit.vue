<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import { confirmAction } from '@/lib/confirm';

interface HolidayEntry {
    id: number;
    date: string;
    dayNumber: string;
    month: string;
    weekday: string;
    name: string;
    isPast: boolean;
}

const props = defineProps<{
    settings: { instituteName: string; lateToleranceMinutes: number };
    year: number;
    holidays: HolidayEntry[];
    missingSuggestions: { date: string; label: string; name: string }[];
}>();

const settingsForm = useForm({
    institute_name: props.settings.instituteName,
    late_tolerance_minutes: props.settings.lateToleranceMinutes,
});

const holidayForm = useForm({
    date: '',
    name: '',
});

const lunarHolidays = ['Tamkharit', 'Maouloud', 'Korité', 'Tabaski', 'Grand Magal de Touba'];

const upcomingCount = computed(() => props.holidays.filter((holiday) => !holiday.isPast).length);

const saveSettings = (): void => {
    settingsForm.put(route('settings.update'), { preserveScroll: true });
};

const addHoliday = (): void => {
    holidayForm.post(route('holidays.store'), {
        preserveScroll: true,
        onSuccess: () => holidayForm.reset(),
    });
};

const addSuggestions = (): void => {
    router.post(route('holidays.suggestions'), { year: props.year }, { preserveScroll: true });
};

const removeHoliday = async (holiday: HolidayEntry): Promise<void> => {
    const confirmed = await confirmAction({
        title: `Retirer « ${holiday.name} » ?`,
        message: 'Ce jour redeviendra un jour de travail normal dans les rapports.',
        confirmLabel: 'Retirer',
        danger: true,
    });

    if (confirmed) {
        router.delete(route('holidays.destroy', holiday.id), { preserveScroll: true });
    }
};

const inputClasses =
    'h-11 w-full rounded-xl border border-line bg-white px-3.5 text-sm outline-none transition placeholder:text-ink/30 focus:border-brand focus:ring-4 focus:ring-brand/15';
</script>

<template>
    <Head title="Réglages" />

    <div>
        <h1 class="text-3xl font-semibold tracking-tight">Réglages</h1>
        <p class="mt-1 text-sm text-ink/60">Valables pour toutes les boutiques.</p>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
        <form class="grid content-start gap-5 rounded-3xl border border-line bg-paper p-6" @submit.prevent="saveSettings">
            <div class="flex items-center gap-3">
                <span class="grid size-9 place-items-center rounded-xl bg-brand/15 text-brand-dark"><Icon name="shield" class="size-4" /></span>
                <div>
                    <h2 class="font-semibold">Général</h2>
                    <p class="text-xs text-ink/50">Repris sur les bornes, les exports et les rapports PDF.</p>
                </div>
            </div>

            <label class="grid gap-1.5 text-sm font-medium">
                Nom de l’institut
                <input v-model="settingsForm.institute_name" required :class="inputClasses">
                <span v-if="settingsForm.errors.institute_name" class="text-xs font-normal text-rose-700">{{ settingsForm.errors.institute_name }}</span>
            </label>

            <label class="grid gap-1.5 text-sm font-medium">
                Tolérance de retard
                <span class="flex items-center gap-3">
                    <input
                        v-model.number="settingsForm.late_tolerance_minutes"
                        type="number"
                        min="0"
                        max="60"
                        required
                        :class="[inputClasses, 'w-28 tabular-nums']"
                    >
                    <span class="font-normal text-ink/60">minutes</span>
                </span>
                <span class="text-xs font-normal text-ink/50">
                    Une arrivée après l’heure prévue + {{ settingsForm.late_tolerance_minutes || 0 }} min compte comme un retard, pour sa durée totale.
                </span>
                <span v-if="settingsForm.errors.late_tolerance_minutes" class="text-xs font-normal text-rose-700">{{ settingsForm.errors.late_tolerance_minutes }}</span>
            </label>

            <button
                type="submit"
                :disabled="settingsForm.processing || !settingsForm.isDirty"
                class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-ink px-6 text-sm font-medium text-cream disabled:opacity-50"
            >
                <Icon name="check" class="size-4" />
                Enregistrer
            </button>
        </form>

        <section class="rounded-3xl border border-line bg-paper p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-xl bg-sky-100 text-sky-800"><Icon name="calendar" class="size-4" /></span>
                    <div>
                        <h2 class="font-semibold">Jours fériés {{ year }}</h2>
                        <p class="text-xs text-ink/50">Ces jours-là, personne n’est compté absent. Un pointage compte en heures supplémentaires.</p>
                    </div>
                </div>
                <div class="flex items-center gap-1 rounded-full border border-line bg-white p-1">
                    <Link
                        :href="route('settings.edit', { annee: year - 1 })"
                        preserve-scroll
                        class="grid size-8 place-items-center rounded-full text-ink/60 hover:bg-cream"
                        aria-label="Année précédente"
                    >
                        <Icon name="chevron-left" class="size-4" />
                    </Link>
                    <span class="px-2 text-sm font-semibold tabular-nums">{{ year }}</span>
                    <Link
                        :href="route('settings.edit', { annee: year + 1 })"
                        preserve-scroll
                        class="grid size-8 place-items-center rounded-full text-ink/60 hover:bg-cream"
                        aria-label="Année suivante"
                    >
                        <Icon name="chevron-right" class="size-4" />
                    </Link>
                </div>
            </div>

            <div v-if="missingSuggestions.length" class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-brand/40 bg-brand/5 px-4 py-3">
                <p class="text-sm text-ink/70">
                    <span class="font-medium text-ink">{{ missingSuggestions.length }} fête(s) à date connue</span> pas encore ajoutée(s) :
                    {{ missingSuggestions.map((suggestion) => `${suggestion.name} (${suggestion.label})`).join(', ') }}.
                </p>
                <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-full bg-brand px-4 text-sm font-semibold text-ink" @click="addSuggestions">
                    <Icon name="plus" class="size-4" />
                    Tout ajouter
                </button>
            </div>

            <ul class="mt-5 grid gap-2 sm:grid-cols-2">
                <li
                    v-for="holiday in holidays"
                    :key="holiday.id"
                    class="flex items-center gap-3 rounded-2xl border border-line bg-white px-3 py-2.5"
                    :class="{ 'opacity-55': holiday.isPast }"
                >
                    <span class="grid w-12 shrink-0 place-items-center rounded-xl bg-cream py-1.5 leading-none">
                        <span class="text-lg font-semibold tabular-nums">{{ holiday.dayNumber }}</span>
                        <span class="text-[11px] text-ink/55 uppercase">{{ holiday.month }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium">{{ holiday.name }}</span>
                        <span class="block text-xs text-ink/50">{{ holiday.weekday }}</span>
                    </span>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-full text-ink/40 hover:bg-rose-50 hover:text-rose-700"
                        :aria-label="`Retirer ${holiday.name}`"
                        @click="removeHoliday(holiday)"
                    >
                        <Icon name="x" class="size-4" />
                    </button>
                </li>
                <li v-if="holidays.length === 0" class="rounded-2xl bg-cream/70 px-4 py-6 text-center text-sm text-ink/55 sm:col-span-2">
                    Aucun jour férié enregistré pour {{ year }}.
                </li>
            </ul>
            <p v-if="holidays.length" class="mt-3 text-xs text-ink/50">{{ upcomingCount }} à venir sur {{ holidays.length }}.</p>

            <form class="mt-6 grid gap-3 border-t border-line pt-5" @submit.prevent="addHoliday">
                <p class="text-sm font-medium">Ajouter un jour férié</p>
                <p class="-mt-2 text-xs text-ink/50">
                    Les fêtes musulmanes suivent le calendrier lunaire : ajoutez-les dès que la date officielle est annoncée.
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="name in lunarHolidays"
                        :key="name"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs transition"
                        :class="holidayForm.name === name ? 'border-ink bg-ink text-cream' : 'border-line bg-white text-ink/70 hover:border-ink/30'"
                        @click="holidayForm.name = name"
                    >
                        {{ name }}
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)_auto]">
                    <input v-model="holidayForm.date" type="date" required aria-label="Date" :class="inputClasses">
                    <input v-model="holidayForm.name" required placeholder="Nom, ex. Tabaski" aria-label="Nom" :class="inputClasses">
                    <button
                        type="submit"
                        :disabled="holidayForm.processing"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-ink px-5 text-sm font-medium text-cream disabled:opacity-60"
                    >
                        <Icon name="plus" class="size-4" />
                        Ajouter
                    </button>
                </div>
                <span v-if="holidayForm.errors.date || holidayForm.errors.name" class="text-xs text-rose-700">{{ holidayForm.errors.date ?? holidayForm.errors.name }}</span>
            </form>
        </section>
    </div>
</template>
