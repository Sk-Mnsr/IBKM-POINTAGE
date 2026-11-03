<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDuration, punchCircleClasses, punchIcon } from '@/lib/format';
import type { DailyAttendance, DayPunch, PunchCorrectionEntry, PunchTypeName, PunchTypeValue } from '@/types';

const props = defineProps<{
    employeeId: number;
    employeeName: string;
    day: DailyAttendance;
    punchTypes: PunchTypeValue[];
    corrections: PunchCorrectionEntry[];
}>();

const emit = defineEmits<{ close: [] }>();

const page = usePage();
const reason = ref('');
const processing = ref(false);
const editingId = ref<number | null>(null);
const editForm = reactive({ time: '', type: 'arrival' as PunchTypeName });

const nextType = (): PunchTypeName => {
    const last = props.day.punches.at(-1)?.type.value;

    switch (last) {
        case 'arrival':
        case 'break_end':
            return 'departure';
        case 'break_start':
            return 'break_end';
        default:
            return 'arrival';
    }
};

const scheduleBound = (type: PunchTypeName): string => {
    const [start, end] = props.day.scheduleLabel.split(' – ');

    if (!end) {
        return '';
    }

    return type === 'arrival' ? start : type === 'departure' ? end : '';
};

const newPunch = reactive({ type: nextType(), time: scheduleBound(nextType()) });

watch(
    () => props.day.punches.length,
    () => {
        newPunch.type = nextType();
        newPunch.time = scheduleBound(newPunch.type);
        editingId.value = null;
    },
);

const errors = computed(() => page.props.errors);
const reasonMissing = computed(() => reason.value.trim().length < 3);

const submitOptions = {
    preserveScroll: true,
    preserveState: true,
    onStart: () => {
        processing.value = true;
    },
    onFinish: () => {
        processing.value = false;
    },
};

const addPunch = (): void => {
    router.post(
        route('punches.corrections.store', props.employeeId),
        { date: props.day.date, time: newPunch.time, type: newPunch.type, reason: reason.value },
        submitOptions,
    );
};

const startEditing = (punch: DayPunch): void => {
    editingId.value = punch.id;
    editForm.time = punch.time;
    editForm.type = punch.type.value;
};

const saveEdit = (punch: DayPunch): void => {
    router.put(route('punches.corrections.update', punch.id), { time: editForm.time, type: editForm.type, reason: reason.value }, submitOptions);
};

const deletePunch = (punch: DayPunch): void => {
    if (!window.confirm(`Supprimer « ${punch.type.label} » de ${punch.time} ?`)) {
        return;
    }

    router.delete(route('punches.corrections.destroy', punch.id), { ...submitOptions, data: { reason: reason.value } });
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const fieldClasses = 'h-10 rounded-xl border border-line bg-white px-3 text-sm outline-none focus:border-brand';
</script>

<template>
    <div class="fixed inset-0 z-30 flex justify-end bg-ink/40 backdrop-blur-sm" @click.self="emit('close')">
        <aside class="flex h-full w-full max-w-lg flex-col overflow-y-auto bg-paper shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="correction-title">
            <header class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-line bg-paper px-6 py-5">
                <div>
                    <p class="text-sm text-ink/55">{{ employeeName }}</p>
                    <h2 id="correction-title" class="text-xl font-semibold">Corriger · {{ day.weekdayLong }} {{ day.monthDay }}</h2>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                        <StatusBadge :tone="day.status.tone" :label="day.status.label" />
                        <span class="text-ink/50">Prévu {{ day.scheduleLabel }} · travaillé {{ formatDuration(day.workedMinutes, '0 min') }}</span>
                    </div>
                </div>
                <button type="button" class="grid size-9 place-items-center rounded-full hover:bg-cream" aria-label="Fermer" @click="emit('close')">
                    <Icon name="x" class="size-4" />
                </button>
            </header>

            <div class="grid gap-6 px-6 py-5">
                <label class="grid gap-1.5 text-sm font-medium">
                    Motif de la correction
                    <textarea
                        v-model="reason"
                        rows="2"
                        placeholder="Ex. : oubli de pointer le départ, confirmé par l'employée"
                        class="rounded-xl border border-line bg-white px-3 py-2 text-sm font-normal outline-none focus:border-brand"
                    />
                    <span class="text-xs font-normal" :class="errors.reason ? 'text-rose-700' : 'text-ink/45'">
                        {{ errors.reason ?? 'Obligatoire : il sera conservé dans le journal des corrections.' }}
                    </span>
                </label>

                <div
                    v-if="errors.punch || errors.time || errors.type"
                    class="flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900"
                    role="alert"
                >
                    <Icon name="alert" class="size-4 shrink-0" />
                    {{ errors.punch ?? errors.time ?? errors.type }}
                </div>

                <section>
                    <h3 class="text-sm font-semibold">Pointages de la journée</h3>
                    <ul class="mt-3 grid gap-2">
                        <li v-for="punch in day.punches" :key="punch.id" class="rounded-2xl border border-line bg-white p-3">
                            <div v-if="editingId !== punch.id" class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-full" :class="punchCircleClasses(punch.type.value)">
                                    <Icon :name="punchIcon(punch.type.value)" class="size-4" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium">{{ punch.type.label }}</span>
                                    <span v-if="punch.isManual || punch.isOffline" class="block text-xs text-ink/45">
                                        {{ punch.isManual ? 'Saisi par le gérant' : 'Envoyé hors ligne' }}
                                    </span>
                                </span>
                                <span class="text-base font-semibold tabular-nums">{{ punch.time }}</span>
                                <button type="button" class="grid size-8 place-items-center rounded-full text-ink/50 hover:bg-cream hover:text-ink" aria-label="Modifier" @click="startEditing(punch)">
                                    <Icon name="pencil" class="size-4" />
                                </button>
                                <button
                                    type="button"
                                    :disabled="processing || reasonMissing"
                                    class="grid size-8 place-items-center rounded-full text-ink/50 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-30"
                                    :title="reasonMissing ? 'Indiquez d’abord le motif' : 'Supprimer'"
                                    aria-label="Supprimer"
                                    @click="deletePunch(punch)"
                                >
                                    <Icon name="x" class="size-4" />
                                </button>
                            </div>

                            <div v-else class="grid gap-2">
                                <div class="grid grid-cols-[minmax(0,1fr)_7rem] gap-2">
                                    <select v-model="editForm.type" :class="fieldClasses" aria-label="Type de pointage">
                                        <option v-for="type in punchTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                                    </select>
                                    <input v-model="editForm.time" type="time" :class="[fieldClasses, 'tabular-nums']" aria-label="Heure">
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="h-9 rounded-xl px-3 text-sm text-ink/60 hover:text-ink" @click="editingId = null">Annuler</button>
                                    <button
                                        type="button"
                                        :disabled="processing || reasonMissing"
                                        class="h-9 rounded-xl bg-ink px-4 text-sm font-medium text-cream disabled:opacity-40"
                                        @click="saveEdit(punch)"
                                    >
                                        Enregistrer
                                    </button>
                                </div>
                            </div>
                        </li>
                        <li v-if="day.punches.length === 0" class="rounded-2xl bg-cream/70 px-4 py-3 text-sm text-ink/55">Aucun pointage ce jour-là.</li>
                    </ul>
                </section>

                <section class="rounded-2xl border border-dashed border-brand/50 bg-brand/5 p-4">
                    <h3 class="text-sm font-semibold">Ajouter un pointage oublié</h3>
                    <div class="mt-3 grid grid-cols-[minmax(0,1fr)_7rem] gap-2">
                        <select v-model="newPunch.type" :class="fieldClasses" aria-label="Type du pointage à ajouter">
                            <option v-for="type in punchTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <input v-model="newPunch.time" type="time" required :class="[fieldClasses, 'tabular-nums']" aria-label="Heure du pointage à ajouter">
                    </div>
                    <button
                        type="button"
                        :disabled="processing || reasonMissing || newPunch.time === ''"
                        class="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl bg-brand text-sm font-semibold text-ink hover:bg-brand/90 disabled:opacity-40"
                        @click="addPunch"
                    >
                        <Icon name="plus" class="size-4" />
                        Ajouter le pointage
                    </button>
                    <p v-if="reasonMissing" class="mt-2 text-center text-xs text-ink/45">Indiquez d’abord le motif ci-dessus.</p>
                </section>

                <section v-if="corrections.length">
                    <h3 class="text-sm font-semibold">Corrections déjà faites ce jour</h3>
                    <ul class="mt-3 grid gap-2">
                        <li v-for="correction in corrections" :key="correction.id" class="rounded-2xl bg-cream/70 px-4 py-3 text-sm">
                            <p>
                                <span class="font-medium">{{ correction.actionLabel }}</span>
                                · {{ correction.summary }}
                            </p>
                            <p class="mt-0.5 text-xs text-ink/55">« {{ correction.reason }} » · {{ correction.author }}, le {{ correction.at }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </aside>
    </div>
</template>
