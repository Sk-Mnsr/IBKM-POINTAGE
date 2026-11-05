<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';
import KioskLoginPunch from '@/components/KioskLoginPunch.vue';
import { confirmAction } from '@/lib/confirm';
import qrcode from '@/vendor/qrcode.js';
import type { KioskPunch } from '@/types';

interface CodeBatch {
    offset: number;
    windowSeconds: number;
    tokens: { window: number; token: string }[];
}

const props = defineProps<{
    kioskName: string;
    shopName: string | null;
    kioskId: string;
    token: string;
    expiresIn: number;
    windowSeconds: number;
    tokenUrl: string;
    tokensUrl: string;
    activityUrl: string;
    isPaired: boolean;
    pairUrl: string;
    identifyUrl: string;
    kioskPunchUrl: string;
    scanUrl: string;
    lastPunchId: number;
}>();

const page = usePage();

const steps = [
    { icon: 'camera', title: "Ouvrez l'appareil photo", detail: 'ou l’onglet « Pointer » de votre espace' },
    { icon: 'qr', title: 'Visez ce code', detail: `il change toutes les ${props.windowSeconds} secondes` },
    { icon: 'check-circle', title: 'Validez votre action', detail: 'arrivée, pause ou départ' },
];

const storageKey = `king-maelick.kiosk-codes.${props.kioskId}`;

const loadBatch = (): CodeBatch | null => {
    try {
        return JSON.parse(localStorage.getItem(storageKey) ?? 'null') as CodeBatch | null;
    } catch {
        return null;
    }
};

const batch = ref<CodeBatch | null>(loadBatch());
const currentToken = ref(props.token);
const remaining = ref(props.expiresIn);
const hasCurrentCode = ref(true);
const now = ref(new Date());
const offline = ref(false);
const isFullscreen = ref(false);
const greeting = ref<KioskPunch | null>(null);
const queue: KioskPunch[] = [];
const greetedIds = new Set<number>();
let lastPunchId = props.lastPunchId;
let refreshing = false;
let clockTimer: number | undefined;
let batchTimer: number | undefined;
let activityTimer: number | undefined;
let greetingTimer: number | undefined;
let audioContext: AudioContext | null = null;
let wakeLock: WakeLockSentinel | null = null;

const qrMarkup = computed(() => {
    const code = qrcode(0, 'M');
    code.addData(`${props.scanUrl}?code=${encodeURIComponent(currentToken.value)}`);
    code.make();

    return code.createSvgTag({ cellSize: 8, margin: 1, scalable: true, alt: 'Code de pointage' });
});

const clock = computed(() => now.value.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }));
const dateLabel = computed(() => {
    const label = now.value.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });

    return label.charAt(0).toUpperCase() + label.slice(1);
});
const ringOffset = computed(() => 100 - Math.min(100, (remaining.value / props.windowSeconds) * 100));

const greetingTheme = computed(() => {
    switch (greeting.value?.type) {
        case 'break_start':
        case 'break_end':
            return { card: 'bg-amber-300 text-ink', icon: 'coffee' };
        case 'departure':
            return { card: 'bg-cream text-ink', icon: 'logout' };
        default:
            return { card: 'bg-brand text-ink', icon: 'check' };
    }
});

const validUntil = computed(() => {
    const last = batch.value?.tokens.at(-1);

    if (!batch.value || !last) {
        return null;
    }

    const end = new Date((last.window + 1) * batch.value.windowSeconds * 1000 - batch.value.offset);

    return end.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
});

const fetchBatch = async (): Promise<void> => {
    try {
        const response = await fetch(props.tokensUrl, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error('codes');
        }

        const data: { window_seconds: number; server_time: number; tokens: { window: number; token: string }[] } = await response.json();
        batch.value = { offset: data.server_time - Date.now(), windowSeconds: data.window_seconds, tokens: data.tokens };
        localStorage.setItem(storageKey, JSON.stringify(batch.value));
        offline.value = false;
    } catch {
        offline.value = true;
    }
};

const refreshToken = async (): Promise<void> => {
    if (refreshing) {
        return;
    }

    refreshing = true;

    try {
        const response = await fetch(props.tokenUrl, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error('token');
        }

        const data: { token: string; expires_in: number } = await response.json();
        currentToken.value = data.token;
        hasCurrentCode.value = true;
        offline.value = false;
    } catch {
        hasCurrentCode.value = false;
        offline.value = true;
    } finally {
        refreshing = false;
    }
};

/**
 * Picks the code of the current window from the stored batch, aligned on the server clock,
 * so the tablet keeps working when the connection drops.
 */
const tick = (): void => {
    now.value = new Date();

    const seconds = batch.value?.windowSeconds ?? props.windowSeconds;
    const serverSeconds = (Date.now() + (batch.value?.offset ?? 0)) / 1000;
    const window = Math.floor(serverSeconds / seconds);
    remaining.value = Math.max(1, Math.ceil(seconds - (serverSeconds % seconds)));

    const stored = batch.value?.tokens.find((entry) => entry.window === window);

    if (stored) {
        currentToken.value = stored.token;
        hasCurrentCode.value = true;

        return;
    }

    if (remaining.value === seconds || !hasCurrentCode.value) {
        refreshToken();
    }
};

const connectionState = computed(() => {
    if (!offline.value) {
        return { label: 'Borne connectée', pill: 'bg-brand/15 text-brand', dot: 'bg-brand' };
    }

    if (hasCurrentCode.value && validUntil.value) {
        return {
            label: `Hors ligne · codes de secours jusqu'à ${validUntil.value}`,
            pill: 'bg-amber-400/15 text-amber-200',
            dot: 'bg-amber-400',
        };
    }

    return { label: 'Hors ligne · reconnectez la tablette', pill: 'bg-rose-500/15 text-rose-200', dot: 'bg-rose-400' };
});

const pairTablet = (): void => {
    router.post(props.pairUrl);
};

const unpairTablets = async (): Promise<void> => {
    const confirmed = await confirmAction({
        title: 'Désappairer toutes les tablettes ?',
        message: 'Les tablettes de cette borne n’afficheront plus le QR code tant qu’un gérant ne les aura pas appairées à nouveau.',
        confirmLabel: 'Désappairer',
        danger: true,
    });

    if (confirmed) {
        router.delete(props.pairUrl);
    }
};

const chime = (): void => {
    try {
        audioContext ??= new AudioContext();
        const start = audioContext.currentTime;

        [660, 880].forEach((frequency, index) => {
            const oscillator = audioContext!.createOscillator();
            const gain = audioContext!.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start + index * 0.14);
            gain.gain.exponentialRampToValueAtTime(0.12, start + index * 0.14 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + index * 0.14 + 0.3);
            oscillator.connect(gain).connect(audioContext!.destination);
            oscillator.start(start + index * 0.14);
            oscillator.stop(start + index * 0.14 + 0.32);
        });
    } catch {
        // Audio stays blocked until someone touches the screen once.
    }
};

const speak = (message: string): void => {
    if (!('speechSynthesis' in window)) {
        return;
    }

    const utterance = new SpeechSynthesisUtterance(message);
    utterance.lang = 'fr-FR';
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(utterance);
};

const showNextGreeting = (): void => {
    const next = queue.shift();

    if (!next) {
        greeting.value = null;

        return;
    }

    greetedIds.add(next.id);
    greeting.value = next;
    chime();
    speak(next.message);
    greetingTimer = window.setTimeout(showNextGreeting, 4500);
};

const pollActivity = async (): Promise<void> => {
    try {
        const response = await fetch(`${props.activityUrl}?apres=${lastPunchId}`, { headers: { Accept: 'application/json' } });

        if (!response.ok) {
            throw new Error('activity');
        }

        const data: { punches: KioskPunch[] } = await response.json();

        if (data.punches.length === 0) {
            return;
        }

        lastPunchId = data.punches[data.punches.length - 1].id;
        queue.push(...data.punches.filter((punch) => !greetedIds.has(punch.id) && !queue.some((queued) => queued.id === punch.id)));

        if (!greeting.value) {
            showNextGreeting();
        }
    } catch {
        offline.value = true;
    }
};

const greetPunchedAtKiosk = (punch: KioskPunch): void => {
    queue.push(punch);

    if (!greeting.value) {
        showNextGreeting();
    }
};

const keepScreenAwake = async (): Promise<void> => {
    try {
        wakeLock = (await navigator.wakeLock?.request('screen')) ?? null;
    } catch {
        wakeLock = null;
    }
};

const toggleFullscreen = async (): Promise<void> => {
    audioContext ??= new AudioContext();
    await audioContext.resume();

    if (document.fullscreenElement) {
        await document.exitFullscreen();

        return;
    }

    await document.documentElement.requestFullscreen?.();
    keepScreenAwake();
};

const onFullscreenChange = (): void => {
    isFullscreen.value = document.fullscreenElement !== null;
};

const onVisibilityChange = (): void => {
    if (document.visibilityState === 'visible') {
        keepScreenAwake();
        fetchBatch();
    }
};

onMounted(() => {
    tick();
    clockTimer = window.setInterval(tick, 1000);
    fetchBatch();
    batchTimer = window.setInterval(fetchBatch, 10 * 60 * 1000);

    activityTimer = window.setInterval(pollActivity, 3000);
    keepScreenAwake();
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onBeforeUnmount(() => {
    window.clearInterval(clockTimer);
    window.clearInterval(batchTimer);
    window.clearInterval(activityTimer);
    window.clearTimeout(greetingTimer);
    wakeLock?.release();
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <Head :title="`Borne · ${shopName ?? kioskName}`" />

    <main class="relative min-h-screen overflow-hidden bg-ink text-cream select-none">
        <div class="pointer-events-none absolute -top-48 -left-40 size-[38rem] rounded-full bg-brand/20 blur-3xl" />
        <div class="pointer-events-none absolute -right-40 -bottom-56 size-[42rem] rounded-full bg-brand/10 blur-3xl" />

        <div class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-6 sm:px-10 sm:py-8">
            <div v-if="page.props.flash.status" class="mb-4 flex items-center gap-3 rounded-2xl bg-brand/15 px-4 py-3 text-sm text-brand">
                <Icon name="check" class="size-4" />
                {{ page.props.flash.status }}
            </div>
            <div v-if="!isPaired" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300/30 bg-amber-300/10 px-4 py-3 text-sm text-amber-100">
                <span class="flex items-center gap-2">
                    <Icon name="alert" class="size-4" />
                    Cette tablette n'est pas encore appairée : la borne ne s'affichera plus après votre déconnexion.
                </span>
                <button type="button" class="rounded-full bg-amber-300 px-4 py-1.5 font-semibold text-ink" @click="pairTablet">Appairer cette tablette</button>
            </div>

            <header class="flex items-start justify-between gap-6">
                <div class="grid gap-2">
                    <AppLogo variant="light" class="w-48 sm:w-60" />
                    <p v-if="shopName" class="pl-1 text-sm font-medium tracking-[0.2em] text-brand uppercase">{{ shopName }}</p>
                </div>
                <div class="text-right">
                    <p class="text-5xl font-semibold tracking-tight tabular-nums sm:text-6xl">{{ clock }}</p>
                    <p class="mt-1 text-base text-cream/60 sm:text-lg">{{ dateLabel }}</p>
                </div>
            </header>

            <section class="grid flex-1 items-center gap-10 py-8 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-20">
                <div class="order-2 lg:order-1">
                    <p class="text-sm font-semibold tracking-[0.25em] text-brand uppercase">Pointage du personnel</p>
                    <h1 class="mt-4 max-w-xl text-4xl leading-[1.1] font-semibold tracking-tight sm:text-5xl xl:text-6xl">
                        Scannez le code pour pointer votre présence
                    </h1>

                    <ol class="mt-10 grid max-w-xl gap-3">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.title"
                            class="flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.04] px-5 py-4 backdrop-blur-sm"
                        >
                            <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand/15 text-brand">
                                <Icon :name="step.icon" class="size-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg font-semibold">{{ step.title }}</p>
                                <p class="text-sm text-cream/55">{{ step.detail }}</p>
                            </div>
                            <span class="text-3xl font-semibold text-white/10 tabular-nums">{{ index + 1 }}</span>
                        </li>
                    </ol>

                    <KioskLoginPunch class="mt-6" :identify-url="identifyUrl" :punch-url="kioskPunchUrl" @punched="greetPunchedAtKiosk" />
                </div>

                <div class="order-1 flex flex-col items-center gap-5 lg:order-2">
                    <div class="relative grid size-[min(78vw,28rem)] place-items-center">
                        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
                            <circle cx="50" cy="50" r="48.5" fill="none" stroke="currentColor" stroke-width="1.2" class="text-white/10" />
                            <circle
                                cx="50"
                                cy="50"
                                r="48.5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                stroke-linecap="round"
                                pathLength="100"
                                stroke-dasharray="100"
                                :stroke-dashoffset="ringOffset"
                                class="text-brand transition-[stroke-dashoffset] duration-1000 ease-linear"
                            />
                        </svg>
                        <div class="relative w-[68%]">
                            <div
                                class="rounded-[1.75rem] bg-white p-4 shadow-2xl shadow-brand/20 transition"
                                :class="{ 'opacity-15 blur-sm': !hasCurrentCode }"
                                v-html="qrMarkup"
                            />
                            <div v-if="!hasCurrentCode" class="absolute inset-0 grid place-items-center px-6 text-center">
                                <p class="text-lg font-semibold">Codes indisponibles<br><span class="text-sm font-normal text-cream/70">Reconnectez la tablette à Internet</span></p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-cream/60">
                        Nouveau code dans <span class="font-semibold text-cream tabular-nums">{{ remaining }} s</span>
                    </p>
                </div>
            </section>

            <footer class="flex flex-wrap items-center justify-between gap-4 text-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 font-medium" :class="connectionState.pill">
                        <span class="relative flex size-2">
                            <span v-if="!offline" class="absolute inline-flex size-full animate-ping rounded-full bg-brand opacity-60" />
                            <span class="relative inline-flex size-2 rounded-full" :class="connectionState.dot" />
                        </span>
                        {{ connectionState.label }}
                    </span>
                    <span class="hidden items-center gap-2 text-cream/45 sm:inline-flex">
                        <Icon name="shield" class="size-4" />
                        Code unique et sécurisé · {{ page.props.app.instituteName }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                <button
                    v-if="isPaired && page.props.auth.user && page.props.auth.user.role !== 'staff'"
                    type="button"
                    class="rounded-full px-3 py-2 text-xs text-cream/40 hover:text-rose-200"
                    @click="unpairTablets"
                >
                    Désappairer
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-cream/70 transition hover:bg-white/5 hover:text-cream"
                    @click="toggleFullscreen"
                >
                    <Icon :name="isFullscreen ? 'minimize' : 'maximize'" class="size-4" />
                    {{ isFullscreen ? 'Quitter le plein écran' : 'Plein écran et son' }}
                </button>
                </div>
            </footer>
        </div>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="greeting" class="fixed inset-0 z-20 grid place-items-center bg-ink/85 px-6 backdrop-blur-md" role="status" aria-live="assertive">
                <div
                    :key="greeting.id"
                    class="greeting-card w-full max-w-2xl rounded-[2.5rem] px-10 py-14 text-center shadow-2xl sm:px-16"
                    :class="greetingTheme.card"
                >
                    <span class="mx-auto grid size-24 place-items-center rounded-full bg-ink text-cream">
                        <Icon :name="greetingTheme.icon" class="size-12" />
                    </span>
                    <p class="mt-8 text-lg font-semibold tracking-[0.2em] uppercase opacity-70">{{ greeting.label }} · {{ greeting.time }}</p>
                    <p class="mt-3 text-5xl font-semibold tracking-tight sm:text-7xl">{{ greeting.message }}</p>
                    <p class="mt-6 text-lg opacity-70">Pointage validé</p>
                </div>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
.greeting-card {
    animation: greeting-pop 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.2);
}

@keyframes greeting-pop {
    from {
        transform: scale(0.85) translateY(1.5rem);
        opacity: 0;
    }

    to {
        transform: scale(1) translateY(0);
        opacity: 1;
    }
}
</style>
