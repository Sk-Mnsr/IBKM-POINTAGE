<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref } from 'vue';
import Icon from '@/components/Icon.vue';
import { firstValidationError, postJson } from '@/lib/http';
import type { KioskPunch, PunchTypeName, PunchTypeValue } from '@/types';

const props = defineProps<{ identifyUrl: string; punchUrl: string }>();

const emit = defineEmits<{ punched: [punch: KioskPunch] }>();

const INACTIVITY_SECONDS = 45;

const open = ref(false);
const step = ref<'login' | 'choose'>('login');
const phone = ref('');
const password = ref('');
const error = ref<string | null>(null);
const processing = ref(false);
const ticket = ref<string | null>(null);
const givenName = ref('');
const actions = ref<PunchTypeValue[]>([]);
const closedReason = ref<string | null>(null);
const today = ref<{ label: string; time: string }[]>([]);
const phoneInput = ref<HTMLInputElement | null>(null);
let inactivityTimer: number | undefined;

const actionTheme: Record<PunchTypeName, { button: string; icon: string }> = {
    arrival: { button: 'bg-brand text-ink hover:bg-brand/90', icon: 'login' },
    break_start: { button: 'bg-amber-300 text-ink hover:bg-amber-200', icon: 'coffee' },
    break_end: { button: 'bg-amber-300 text-ink hover:bg-amber-200', icon: 'timer' },
    departure: { button: 'bg-cream text-ink hover:bg-white', icon: 'logout' },
};

/**
 * The tablet is shared: nothing typed by an employee may stay on screen once they walk away.
 */
const reset = (): void => {
    window.clearTimeout(inactivityTimer);
    step.value = 'login';
    phone.value = '';
    password.value = '';
    error.value = null;
    processing.value = false;
    ticket.value = null;
    givenName.value = '';
    actions.value = [];
    closedReason.value = null;
    today.value = [];
};

const close = (): void => {
    reset();
    open.value = false;
};

const keepAlive = (): void => {
    window.clearTimeout(inactivityTimer);
    inactivityTimer = window.setTimeout(close, INACTIVITY_SECONDS * 1000);
};

const start = async (): Promise<void> => {
    reset();
    open.value = true;
    keepAlive();
    await nextTick();
    phoneInput.value?.focus();
};

const handleFailure = async (response: Response): Promise<void> => {
    if (response.status === 419) {
        error.value = 'La tablette doit être rechargée. Un instant…';
        window.setTimeout(() => window.location.reload(), 1500);

        return;
    }

    if (response.status === 429) {
        error.value = 'Trop de tentatives. Patientez une minute avant de réessayer.';

        return;
    }

    if (response.status !== 422) {
        error.value = 'Le pointage n’a pas pu être enregistré. Réessayez ou utilisez votre téléphone.';

        return;
    }

    const failure = await firstValidationError(response, 'Pointage refusé.');
    error.value = failure.message;

    if (failure.field === 'ticket') {
        step.value = 'login';
        ticket.value = null;
    }
};

const identify = async (): Promise<void> => {
    if (processing.value) {
        return;
    }

    if (!navigator.onLine) {
        error.value = 'Pas de connexion Internet : ce mode de pointage en a besoin. Utilisez votre téléphone.';

        return;
    }

    processing.value = true;
    error.value = null;
    keepAlive();

    try {
        const response = await postJson(props.identifyUrl, { phone: phone.value, password: password.value });
        password.value = '';

        if (!response.ok) {
            await handleFailure(response);

            return;
        }

        const data: {
            ticket: string;
            givenName: string;
            actions: PunchTypeValue[];
            closedReason: string | null;
            today: { label: string; time: string }[];
        } = await response.json();
        ticket.value = data.ticket;
        givenName.value = data.givenName;
        actions.value = data.actions;
        closedReason.value = data.closedReason;
        today.value = data.today;
        step.value = 'choose';
    } catch {
        error.value = 'Pas de connexion Internet : ce mode de pointage en a besoin. Utilisez votre téléphone.';
    } finally {
        processing.value = false;
    }
};

const punch = async (type: PunchTypeName): Promise<void> => {
    if (processing.value || !ticket.value) {
        return;
    }

    processing.value = true;
    error.value = null;
    keepAlive();

    try {
        const response = await postJson(props.punchUrl, { ticket: ticket.value, type });

        if (!response.ok) {
            await handleFailure(response);

            return;
        }

        const recorded: KioskPunch = await response.json();
        close();
        emit('punched', recorded);
    } catch {
        error.value = 'Connexion perdue. Réessayez ou utilisez votre téléphone.';
    } finally {
        processing.value = false;
    }
};

onBeforeUnmount(() => window.clearTimeout(inactivityTimer));
</script>

<template>
    <div>
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full px-1 py-1 text-sm text-cream/35 transition hover:text-cream/80"
        @click="start"
    >
        <Icon name="smartphone" class="size-4" />
        Pas de téléphone ? Pointer avec mes identifiants
    </button>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-30 grid place-items-center bg-ink/90 px-4 text-cream backdrop-blur-md"
                role="dialog"
                aria-modal="true"
                aria-labelledby="kiosk-login-title"
                @pointerdown="keepAlive"
                @keydown="keepAlive"
                @keydown.esc="close"
            >
                <div class="w-full max-w-lg rounded-[2rem] border border-white/10 bg-[#1c1f1c] p-8 shadow-2xl sm:p-10">
                    <form v-if="step === 'login'" class="grid gap-5" autocomplete="off" @submit.prevent="identify">
                        <div>
                            <span class="grid size-12 place-items-center rounded-2xl bg-brand/15 text-brand"><Icon name="smartphone" class="size-6" /></span>
                            <h2 id="kiosk-login-title" class="mt-5 text-3xl font-semibold tracking-tight">Pointer sans téléphone</h2>
                            <p class="mt-1.5 text-cream/55">Saisissez le numéro et le mot de passe de votre compte.</p>
                        </div>

                        <label class="grid gap-2 text-sm font-medium text-cream/70">
                            Téléphone
                            <input
                                ref="phoneInput"
                                v-model="phone"
                                inputmode="numeric"
                                autocomplete="off"
                                required
                                placeholder="77 000 00 00"
                                class="h-14 rounded-2xl border border-white/10 bg-white/5 px-5 text-xl tracking-wide text-cream tabular-nums outline-none placeholder:text-cream/20 focus:border-brand"
                            >
                        </label>
                        <label class="grid gap-2 text-sm font-medium text-cream/70">
                            Mot de passe
                            <input
                                v-model="password"
                                type="password"
                                autocomplete="new-password"
                                required
                                class="h-14 rounded-2xl border border-white/10 bg-white/5 px-5 text-xl text-cream outline-none focus:border-brand"
                            >
                        </label>

                        <p v-if="error" class="flex items-start gap-2 rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-100" role="alert">
                            <Icon name="alert" class="mt-0.5 size-4 shrink-0" />
                            {{ error }}
                        </p>

                        <div class="mt-1 flex items-center justify-end gap-2">
                            <button type="button" class="h-13 rounded-full px-6 text-cream/60 hover:text-cream" @click="close">Annuler</button>
                            <button
                                type="submit"
                                :disabled="processing"
                                class="inline-flex h-13 items-center gap-2 rounded-full bg-brand px-7 font-semibold text-ink disabled:opacity-60"
                            >
                                {{ processing ? 'Vérification…' : 'Continuer' }}
                                <Icon name="chevron-right" class="size-5" />
                            </button>
                        </div>
                    </form>

                    <div v-else class="grid gap-6">
                        <div>
                            <p class="text-sm font-semibold tracking-[0.2em] text-brand uppercase">Identifié(e)</p>
                            <h2 id="kiosk-login-title" class="mt-2 text-4xl font-semibold tracking-tight">Bonjour {{ givenName }}</h2>
                            <p class="mt-1.5 text-cream/55">Que souhaitez-vous pointer ?</p>
                        </div>

                        <ul v-if="today.length" class="flex flex-wrap gap-2 text-sm">
                            <li v-for="entry in today" :key="`${entry.label}-${entry.time}`" class="rounded-full bg-white/5 px-3 py-1 text-cream/70">
                                {{ entry.label }} · <span class="tabular-nums">{{ entry.time }}</span>
                            </li>
                        </ul>

                        <div class="grid gap-3">
                            <button
                                v-for="action in actions"
                                :key="action.value"
                                type="button"
                                :disabled="processing"
                                class="inline-flex h-16 items-center justify-center gap-3 rounded-2xl text-xl font-semibold transition disabled:opacity-60"
                                :class="actionTheme[action.value].button"
                                @click="punch(action.value)"
                            >
                                <Icon :name="actionTheme[action.value].icon" class="size-6" />
                                {{ action.buttonLabel }}
                            </button>
                            <p v-if="actions.length === 0" class="rounded-2xl bg-sky-400/10 px-4 py-3 text-sky-100">
                                {{ closedReason ?? 'Aucune action disponible pour le moment.' }}
                            </p>
                        </div>

                        <p v-if="error" class="flex items-start gap-2 rounded-2xl bg-rose-500/15 px-4 py-3 text-sm text-rose-100" role="alert">
                            <Icon name="alert" class="mt-0.5 size-4 shrink-0" />
                            {{ error }}
                        </p>

                        <button type="button" class="justify-self-center text-sm text-cream/45 hover:text-cream" @click="close">Ce n’est pas moi · Annuler</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
    </div>
</template>
