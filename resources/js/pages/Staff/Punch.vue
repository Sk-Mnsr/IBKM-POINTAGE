<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDuration, punchCircleClasses, punchIcon } from '@/lib/format';
import { generateUuid, loadPendingPunches, queuePunch, syncPendingPunches, type PendingPunch } from '@/lib/offline-punches';
import { canScanLive, decodeQrFromPhoto, startLiveScan, type LiveScan } from '@/lib/qr-scanner';
import type { DailyAttendance, PunchTypeName, PunchTypeValue } from '@/types';

const props = defineProps<{
    todayLabel: string;
    actions: PunchTypeValue[];
    attendance: DailyAttendance;
    scannedToken: string;
    punchTypes: PunchTypeValue[];
    givenName: string;
    syncUrl: string;
}>();

const page = usePage();

const codeFromAddress = (): string => new URLSearchParams(window.location.search).get('code') ?? '';

const form = useForm({
    token: props.scannedToken || codeFromAddress(),
    type: '' as PunchTypeName | '',
});

const scannedAt = ref<Date | null>(form.token ? new Date() : null);
const video = ref<HTMLVideoElement | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);
const cameraOpen = ref(false);
const cameraError = ref(false);
const decoding = ref(false);
const scanError = ref<string | null>(null);
const liveScanAvailable = canScanLive();
const isOnline = ref(navigator.onLine);
const pending = ref<PendingPunch[]>(loadPendingPunches());
const offlineNotice = ref<string | null>(null);
const syncNotice = ref<{ tone: 'success' | 'error'; text: string } | null>(null);
const syncing = ref(false);
const lastOfflineType = ref<PunchTypeName | null>(pending.value.at(-1)?.type ?? null);
let liveScan: LiveScan | null = null;

const hasToken = computed(() => form.token !== '');

const nextTypes: Record<PunchTypeName, PunchTypeName[]> = {
    arrival: ['break_start', 'departure'],
    break_start: ['break_end'],
    break_end: ['break_start', 'departure'],
    departure: ['arrival'],
};

const availableActions = computed<PunchTypeValue[]>(() => {
    if (lastOfflineType.value === null) {
        return props.actions;
    }

    return nextTypes[lastOfflineType.value]
        .map((value) => props.punchTypes.find((type) => type.value === value))
        .filter((type): type is PunchTypeValue => type !== undefined);
});

const confirmations: Record<PunchTypeName, (name: string) => string> = {
    arrival: (name) => `Bonjour ${name}`,
    break_start: (name) => `Bonne pause ${name}`,
    break_end: (name) => `Bon retour ${name}`,
    departure: (name) => `Au revoir ${name}`,
};

const actionClasses = (type: PunchTypeName): string => {
    if (type === 'arrival') {
        return 'bg-brand text-ink';
    }

    if (type === 'departure') {
        return 'bg-ink text-cream';
    }

    return 'bg-amber-500 text-ink';
};

const announce = (message: string): void => {
    try {
        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.frequency.value = 880;
        gain.gain.value = 0.05;
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start();
        oscillator.stop(context.currentTime + 0.18);
    } catch {
        // Audio can be blocked until the user interacts with the page.
    }

    if ('speechSynthesis' in window) {
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = 'fr-FR';
        window.speechSynthesis.speak(utterance);
    }
};

const storeOffline = (type: PunchTypeName): void => {
    const punchType = props.punchTypes.find((item) => item.value === type);
    const at = scannedAt.value ?? new Date();

    pending.value = queuePunch({
        uuid: generateUuid(),
        token: form.token,
        type,
        label: punchType?.label ?? type,
        scannedAt: at.toISOString(),
    });

    lastOfflineType.value = type;
    const time = at.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    offlineNotice.value = `${punchType?.label ?? 'Pointage'} enregistré sur le téléphone à ${time}. Il sera envoyé dès le retour du réseau.`;
    announce(confirmations[type](props.givenName));
    form.reset('token');
    scannedAt.value = null;
};

const submit = (type: PunchTypeName): void => {
    if (!hasToken.value) {
        return;
    }

    offlineNotice.value = null;

    if (!navigator.onLine) {
        storeOffline(type);

        return;
    }

    form.type = type;
    form.post(route('punch.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('token');
            scannedAt.value = null;
            lastOfflineType.value = null;
        },
        onNetworkError: () => {
            storeOffline(type);

            return false;
        },
    });
};

const syncNow = async (): Promise<void> => {
    if (syncing.value || pending.value.length === 0 || !navigator.onLine) {
        return;
    }

    syncing.value = true;
    const outcome = await syncPendingPunches(props.syncUrl);
    pending.value = loadPendingPunches();
    syncing.value = false;

    if (outcome.needsLogin) {
        syncNotice.value = { tone: 'error', text: 'Votre session a expiré : reconnectez-vous pour envoyer les pointages en attente.' };

        return;
    }

    if (outcome.rejected.length > 0) {
        const first = outcome.rejected[0];
        syncNotice.value = { tone: 'error', text: `${first.punch.label} non enregistré : ${first.message}` };
    } else if (outcome.sent.length > 0) {
        syncNotice.value = { tone: 'success', text: `${outcome.sent.length} pointage(s) hors ligne envoyé(s).` };
    }

    if (pending.value.length === 0) {
        lastOfflineType.value = null;
        offlineNotice.value = null;
        router.reload({ only: ['attendance', 'actions'] });
    }
};

const extractToken = (value: string): string => {
    try {
        return new URL(value).searchParams.get('code') ?? value;
    } catch {
        return value;
    }
};

const useScannedValue = (value: string): void => {
    form.token = extractToken(value);
    form.clearErrors();
    scannedAt.value = new Date();
    scanError.value = null;
    navigator.vibrate?.(80);

    if (availableActions.value.length === 1) {
        submit(availableActions.value[0].value);
    }
};

const stopCamera = (): void => {
    liveScan?.stop();
    liveScan = null;
    cameraOpen.value = false;
};

const openCamera = async (): Promise<void> => {
    scanError.value = null;
    cameraOpen.value = true;
    await new Promise((resolve) => requestAnimationFrame(resolve));

    if (!video.value) {
        return;
    }

    try {
        liveScan = await startLiveScan(video.value, (value) => {
            cameraOpen.value = false;
            liveScan = null;
            useScannedValue(value);
        });
    } catch {
        cameraOpen.value = false;
        cameraError.value = true;
        scanError.value = "La caméra n'a pas pu s'ouvrir. Autorisez l'accès à la caméra, ou prenez le code en photo.";
    }
};

const takePhoto = (): void => {
    scanError.value = null;
    photoInput.value?.click();
};

const onPhotoTaken = async (event: Event): Promise<void> => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    decoding.value = true;

    try {
        const value = await decodeQrFromPhoto(file);

        if (value) {
            useScannedValue(value);
        } else {
            scanError.value = 'Code non reconnu. Cadrez bien le QR de la tablette, sans reflet, puis reprenez la photo.';
        }
    } catch {
        scanError.value = 'Impossible de lire la photo. Réessayez.';
    } finally {
        decoding.value = false;
    }
};

const onOnline = (): void => {
    isOnline.value = true;
    syncNow();
};

const onOffline = (): void => {
    isOnline.value = false;
};

watch(
    () => page.props.flash.status,
    (status) => {
        if (status) {
            announce(status);
        }
    },
    { immediate: true },
);

onMounted(() => {
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    syncNow();
});

onBeforeUnmount(() => {
    stopCamera();
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
});
</script>

<template>
    <Head title="Pointer" />

    <div class="grid gap-5">
        <div v-if="!isOnline" class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
            <Icon name="wifi-off" class="size-5 shrink-0" />
            Pas de réseau : vos pointages sont gardés sur le téléphone et envoyés automatiquement plus tard.
        </div>

        <div v-if="page.props.flash.status" class="flex items-center gap-4 rounded-3xl bg-brand-dark px-5 py-5 text-white" role="status">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-white/15">
                <Icon name="check" class="size-6" />
            </span>
            <div>
                <p class="text-lg font-semibold">Pointage validé</p>
                <p class="text-white/85">{{ page.props.flash.status }}</p>
            </div>
        </div>

        <div v-if="offlineNotice" class="flex items-center gap-4 rounded-3xl bg-ink px-5 py-5 text-cream" role="status">
            <span class="grid size-12 shrink-0 place-items-center rounded-full bg-brand text-ink">
                <Icon name="check" class="size-6" />
            </span>
            <div>
                <p class="text-lg font-semibold">Pointage enregistré hors ligne</p>
                <p class="text-sm text-cream/75">{{ offlineNotice }}</p>
            </div>
        </div>

        <div
            v-if="syncNotice"
            class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm"
            :class="syncNotice.tone === 'success' ? 'border-brand/40 bg-brand/10 text-brand-dark' : 'border-rose-200 bg-rose-50 text-rose-900'"
            role="status"
        >
            <Icon :name="syncNotice.tone === 'success' ? 'check' : 'alert'" class="shrink-0" />
            {{ syncNotice.text }}
        </div>

        <div v-if="form.errors.token || form.errors.type" class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
            <Icon name="alert" />
            <div class="grid gap-1">
                <p v-if="form.errors.token">{{ form.errors.token }}</p>
                <p v-if="form.errors.type">{{ form.errors.type }}</p>
            </div>
        </div>

        <section class="rounded-3xl bg-ink p-6 text-cream">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-cream/60">{{ todayLabel }}</p>
                    <p class="mt-1 text-3xl font-semibold">{{ attendance.status.label }}</p>
                </div>
                <StatusBadge :tone="attendance.status.tone" :label="attendance.scheduleLabel" />
            </div>
            <dl class="mt-6 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-2xl bg-white/5 px-2 py-3">
                    <dt class="text-xs text-cream/55">Arrivée</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">{{ attendance.firstArrival ?? '—' }}</dd>
                </div>
                <div class="rounded-2xl bg-white/5 px-2 py-3">
                    <dt class="text-xs text-cream/55">Travaillé</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatDuration(attendance.workedMinutes, '0 min') }}</dd>
                </div>
                <div class="rounded-2xl bg-white/5 px-2 py-3">
                    <dt class="text-xs text-cream/55">Pause</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums">{{ formatDuration(attendance.breakMinutes, '0 min') }}</dd>
                </div>
            </dl>
            <p v-if="attendance.lateMinutes > 0" class="mt-4 flex items-center gap-2 text-sm text-amber-300">
                <Icon name="timer" class="size-4" />
                Arrivée avec {{ attendance.lateMinutes }} min de retard
            </p>
        </section>

        <section class="grid gap-4 rounded-3xl border border-line bg-paper p-5">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-brand/15 text-brand-dark">
                    <Icon name="qr" />
                </span>
                <div>
                    <p class="font-semibold">Scanner la borne</p>
                    <p class="text-sm text-ink/60">Visez le code affiché sur la tablette de l'accueil.</p>
                </div>
            </div>

            <div v-show="cameraOpen" class="relative overflow-hidden rounded-2xl bg-ink">
                <video ref="video" autoplay playsinline muted class="aspect-square w-full object-cover" />
                <div class="pointer-events-none absolute inset-10 rounded-3xl border-2 border-white/80 shadow-[0_0_0_999px_rgba(0,0,0,0.35)]" />
                <p class="absolute inset-x-0 bottom-4 text-center text-sm font-medium text-white">Visez le QR de la tablette</p>
                <button type="button" class="absolute top-3 right-3 grid size-9 place-items-center rounded-full bg-black/50 text-white" aria-label="Fermer la caméra" @click="stopCamera">
                    <Icon name="x" class="size-4" />
                </button>
            </div>

            <input ref="photoInput" type="file" accept="image/*" capture="environment" class="hidden" @change="onPhotoTaken">

            <p v-if="hasToken" class="flex items-center gap-2 rounded-xl bg-brand/15 px-4 py-3 text-sm font-medium text-brand-dark">
                <Icon name="check" class="size-4" />
                Code de la borne détecté · choisissez votre action
            </p>
            <template v-else-if="!cameraOpen">
                <button
                    v-if="liveScanAvailable && !cameraError"
                    type="button"
                    class="inline-flex h-14 items-center justify-center gap-2 rounded-2xl bg-brand text-lg font-semibold text-ink"
                    @click="openCamera"
                >
                    <Icon name="scan" />
                    Scanner le QR code
                </button>
                <button
                    type="button"
                    :disabled="decoding"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl font-semibold disabled:opacity-60"
                    :class="liveScanAvailable && !cameraError ? 'h-11 border border-line bg-white text-sm text-ink/70' : 'h-14 bg-brand text-lg text-ink'"
                    @click="takePhoto"
                >
                    <Icon :name="liveScanAvailable && !cameraError ? 'camera' : 'scan'" />
                    <template v-if="decoding">Lecture du QR code…</template>
                    <template v-else-if="liveScanAvailable && !cameraError">Utiliser une photo à la place</template>
                    <template v-else>Scanner le QR code</template>
                </button>
                <p class="text-center text-xs text-ink/50">Vous pouvez aussi viser le code avec l'appareil photo du téléphone.</p>
            </template>

            <p v-if="scanError" class="flex items-start gap-2 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                <Icon name="alert" class="size-4 shrink-0" />
                {{ scanError }}
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    v-for="action in availableActions"
                    :key="action.value"
                    type="button"
                    :disabled="!hasToken || form.processing"
                    class="inline-flex h-14 items-center justify-center gap-2 rounded-2xl text-base font-semibold transition disabled:cursor-not-allowed disabled:bg-ink/10 disabled:text-ink/35"
                    :class="[actionClasses(action.value), { 'sm:col-span-2': availableActions.length === 1 }]"
                    @click="submit(action.value)"
                >
                    <Icon :name="punchIcon(action.value)" />
                    {{ action.buttonLabel }}
                </button>
            </div>
            <p v-if="!hasToken" class="-mt-1 text-center text-xs text-ink/50">Les boutons s'activent après le scan du code de la tablette.</p>
        </section>

        <section v-if="pending.length > 0" class="rounded-3xl border border-amber-200 bg-amber-50/60 p-5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-semibold text-amber-900">En attente d'envoi ({{ pending.length }})</h2>
                <button
                    type="button"
                    :disabled="syncing || !isOnline"
                    class="rounded-full bg-amber-900 px-3 py-1.5 text-xs font-medium text-amber-50 disabled:opacity-50"
                    @click="syncNow"
                >
                    {{ syncing ? 'Envoi…' : 'Envoyer maintenant' }}
                </button>
            </div>
            <ul class="mt-3 grid gap-2">
                <li v-for="punch in pending" :key="punch.uuid" class="flex items-center gap-3 text-sm">
                    <span class="grid size-8 place-items-center rounded-full" :class="punchCircleClasses(punch.type)">
                        <Icon :name="punchIcon(punch.type)" class="size-4" />
                    </span>
                    <span class="flex-1 font-medium">{{ punch.label }}</span>
                    <span class="tabular-nums text-ink/60">
                        {{ new Date(punch.scannedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) }}
                    </span>
                </li>
            </ul>
        </section>

        <section class="rounded-3xl border border-line bg-paper p-5">
            <h2 class="font-semibold">Ma journée</h2>
            <ol class="mt-4 grid gap-4">
                <li v-for="punch in [...attendance.punches].reverse()" :key="punch.id" class="flex items-center gap-3">
                    <span class="grid size-9 place-items-center rounded-full" :class="punchCircleClasses(punch.type.value)">
                        <Icon :name="punchIcon(punch.type.value)" class="size-4" />
                    </span>
                    <span class="flex-1 text-sm font-medium">{{ punch.type.label }}</span>
                    <span class="text-sm tabular-nums text-ink/60">{{ punch.time }}</span>
                </li>
                <li v-if="attendance.punches.length === 0" class="text-sm text-ink/55">Aucun pointage aujourd'hui. Scannez la borne pour valider votre arrivée.</li>
            </ol>
        </section>
    </div>
</template>
