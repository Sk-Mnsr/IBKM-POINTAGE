<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Alert from '@/components/Alert.vue';
import Icon from '@/components/Icon.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { LeaveRequest } from '@/types';

defineProps<{
    leaveRequests: LeaveRequest[];
    types: { value: string; label: string }[];
    today: string;
}>();

const page = usePage();

const form = useForm({
    type: 'paid_leave',
    starts_on: '',
    ends_on: '',
    reason: '',
});

const submit = (): void => {
    form.post(route('staff.leaves.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const cancel = (leave: LeaveRequest): void => {
    if (!window.confirm('Annuler cette demande ?')) {
        return;
    }

    router.delete(route('staff.leaves.destroy', leave.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Mes congés" />

    <div class="grid gap-5">
        <h1 class="text-2xl font-semibold">Mes congés</h1>

        <Alert v-if="page.props.flash.status">{{ page.props.flash.status }}</Alert>
        <Alert v-if="page.props.errors.leave" tone="error">{{ page.props.errors.leave }}</Alert>

        <form class="grid gap-4 rounded-3xl border border-line bg-paper p-5" @submit.prevent="submit">
            <h2 class="font-semibold">Nouvelle demande</h2>

            <fieldset class="grid grid-cols-2 gap-2">
                <legend class="mb-2 text-sm font-medium">Type</legend>
                <label
                    v-for="type in types"
                    :key="type.value"
                    class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-3 text-sm"
                    :class="form.type === type.value ? 'border-ink bg-ink text-cream' : 'border-line bg-white'"
                >
                    <input v-model="form.type" type="radio" :value="type.value" class="sr-only">
                    {{ type.label }}
                </label>
            </fieldset>
            <p v-if="form.errors.type" class="text-sm text-rose-800">{{ form.errors.type }}</p>

            <div class="grid grid-cols-2 gap-3">
                <label class="grid gap-1.5 text-sm font-medium">
                    Du
                    <input v-model="form.starts_on" type="date" :min="today" required class="h-12 rounded-xl border border-line bg-white px-3">
                    <span v-if="form.errors.starts_on" class="font-normal text-rose-800">{{ form.errors.starts_on }}</span>
                </label>
                <label class="grid gap-1.5 text-sm font-medium">
                    Au
                    <input v-model="form.ends_on" type="date" :min="form.starts_on || today" required class="h-12 rounded-xl border border-line bg-white px-3">
                    <span v-if="form.errors.ends_on" class="font-normal text-rose-800">{{ form.errors.ends_on }}</span>
                </label>
            </div>

            <label class="grid gap-1.5 text-sm font-medium">
                Motif (facultatif)
                <textarea v-model="form.reason" rows="3" class="rounded-xl border border-line bg-white px-3 py-2" />
                <span v-if="form.errors.reason" class="font-normal text-rose-800">{{ form.errors.reason }}</span>
            </label>

            <button type="submit" :disabled="form.processing" class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-ink font-semibold text-cream disabled:opacity-60">
                <Icon name="plane" class="size-4" />
                Envoyer la demande
            </button>
        </form>

        <section class="grid gap-3">
            <h2 class="font-semibold">Mes demandes</h2>
            <article v-for="leave in leaveRequests" :key="leave.id" class="flex items-start justify-between gap-4 rounded-2xl border border-line bg-paper p-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-medium">{{ leave.type.label }}</p>
                        <StatusBadge :tone="leave.status.tone" :label="leave.status.label" />
                    </div>
                    <p class="mt-1 text-sm text-ink/65">{{ leave.startsLabel }} → {{ leave.endsLabel }} · {{ leave.dayCount }} jour(s)</p>
                    <p v-if="leave.reviewNote" class="mt-1 text-xs text-ink/55">Réponse du gérant : {{ leave.reviewNote }}</p>
                </div>
                <button v-if="leave.status.value === 'pending'" type="button" class="text-sm text-ink/60 underline" @click="cancel(leave)">Annuler</button>
            </article>
            <p v-if="leaveRequests.length === 0" class="rounded-2xl border border-dashed border-line px-4 py-8 text-center text-sm text-ink/55">Aucune demande pour le moment.</p>
        </section>
    </div>
</template>
