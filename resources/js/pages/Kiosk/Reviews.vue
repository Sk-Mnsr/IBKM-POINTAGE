<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';
import { firstValidationError, postJson } from '@/lib/http';

interface RatingOption {
    value: string;
    label: string;
    tone: string;
}

const props = defineProps<{
    kioskName: string;
    shopName: string | null;
    kioskUrl: string;
    submitUrl: string;
    question: string;
    ratings: RatingOption[];
}>();

const selected = ref<string | null>(null);
const comment = ref('');
const showComment = ref(false);
const processing = ref(false);
const error = ref<string | null>(null);
const thanks = ref(false);
const popping = ref<string | null>(null);
const cooldownUntil = ref(0);
const now = ref(Date.now());
const commentInput = ref<HTMLTextAreaElement | null>(null);
let audioContext: AudioContext | null = null;
let cooldownTimer: ReturnType<typeof window.setInterval> | null = null;

const moods: Record<string, { hint: string; thanks: string; glow: string; chip: string }> = {
    very_satisfied: {
        hint: 'Super, ça nous fait plaisir !',
        thanks: 'Vous nous faites rayonner',
        glow: 'shadow-[0_0_60px_rgba(146,188,53,0.45)]',
        chip: 'bg-brand text-ink',
    },
    satisfied: {
        hint: 'Merci, on retient le coup de pouce.',
        thanks: 'Merci pour votre confiance',
        glow: 'shadow-[0_0_60px_rgba(251,191,36,0.4)]',
        chip: 'bg-amber-300 text-ink',
    },
    unsatisfied: {
        hint: 'On regrette… dites-nous ce qui a cloché.',
        thanks: 'Merci, on va s’améliorer',
        glow: 'shadow-[0_0_60px_rgba(251,113,133,0.4)]',
        chip: 'bg-rose-300 text-ink',
    },
};

const mood = computed(() => (selected.value ? moods[selected.value] : null));

const cooldownSeconds = computed(() => Math.max(0, Math.ceil((cooldownUntil.value - now.value) / 1000)));
const isCoolingDown = computed(() => cooldownSeconds.value > 0);

const sparkles = Array.from({ length: 18 }, (_, index) => ({
    id: index,
    left: `${(index * 17) % 100}%`,
    delay: `${(index % 6) * 0.12}s`,
    size: `${10 + (index % 5) * 4}px`,
    tone: index % 3 === 0 ? 'bg-brand' : index % 3 === 1 ? 'bg-amber-300' : 'bg-cream',
}));

const startCooldownClock = (): void => {
    if (cooldownTimer !== null) {
        return;
    }

    cooldownTimer = window.setInterval(() => {
        now.value = Date.now();

        if (now.value >= cooldownUntil.value && cooldownTimer !== null) {
            window.clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
    }, 250);
};

onUnmounted(() => {
    if (cooldownTimer !== null) {
        window.clearInterval(cooldownTimer);
    }
});

const chime = (value: string): void => {
    try {
        audioContext ??= new AudioContext();
        const start = audioContext.currentTime;
        const notes = value === 'very_satisfied' ? [523, 659, 784] : value === 'satisfied' ? [440, 554] : [330, 294];

        notes.forEach((frequency, index) => {
            const oscillator = audioContext!.createOscillator();
            const gain = audioContext!.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start + index * 0.08);
            gain.gain.exponentialRampToValueAtTime(0.1, start + index * 0.08 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + index * 0.08 + 0.28);
            oscillator.connect(gain).connect(audioContext!.destination);
            oscillator.start(start + index * 0.08);
            oscillator.stop(start + index * 0.08 + 0.3);
        });
    } catch {
        // Audio stays blocked until a first tap.
    }
};

const goBack = (): void => {
    if (window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(props.kioskUrl);
};

const closeComment = (): void => {
    if (processing.value) {
        return;
    }

    showComment.value = false;
    comment.value = '';
    error.value = null;
    selected.value = null;
};

const pick = async (value: string): Promise<void> => {
    if (isCoolingDown.value || processing.value || thanks.value) {
        return;
    }

    selected.value = value;
    popping.value = value;
    error.value = null;
    comment.value = '';
    showComment.value = true;
    chime(value);
    window.setTimeout(() => {
        if (popping.value === value) {
            popping.value = null;
        }
    }, 450);
    await nextTick();
    commentInput.value?.focus();
};

const finishThanks = (): void => {
    selected.value = null;
    comment.value = '';
    showComment.value = false;
    thanks.value = false;
    cooldownUntil.value = Date.now() + 45_000;
    now.value = Date.now();
    startCooldownClock();
};

const submit = async (): Promise<void> => {
    if (!selected.value || processing.value || isCoolingDown.value) {
        return;
    }

    processing.value = true;
    error.value = null;

    try {
        const response = await postJson(props.submitUrl, {
            rating: selected.value,
            comment: comment.value.trim() || null,
        });

        if (response.status === 419) {
            error.value = 'La page doit être rechargée. Un instant…';
            window.setTimeout(() => window.location.reload(), 1500);

            return;
        }

        if (response.status === 429) {
            error.value = 'Trop d’avis envoyés. Patientez une minute.';

            return;
        }

        if (!response.ok) {
            error.value = (await firstValidationError(response, 'Impossible d’enregistrer votre avis.')).message;

            return;
        }

        chime('very_satisfied');
        showComment.value = false;
        thanks.value = true;
        window.setTimeout(finishThanks, 2800);
    } catch {
        error.value = 'Connexion impossible. Réessayez dans un instant.';
    } finally {
        processing.value = false;
    }
};
</script>

<template>
    <Head :title="`Avis client · ${shopName ?? kioskName}`" />

    <main class="relative min-h-screen overflow-hidden bg-ink text-cream select-none">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,rgba(146,188,53,0.18),transparent_55%)]" />
        <div class="pointer-events-none absolute -bottom-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-brand/10 blur-3xl" />

        <div class="relative mx-auto flex min-h-screen w-full max-w-4xl flex-col px-6 py-6 sm:px-10 sm:py-8">
            <header class="flex items-start justify-between gap-6">
                <div class="grid gap-2">
                    <AppLogo variant="light" class="w-44 sm:w-56" />
                    <p v-if="shopName" class="pl-1 text-sm font-medium tracking-[0.2em] text-brand uppercase">{{ shopName }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border border-white/15 px-4 py-2 text-sm text-cream/70 transition hover:bg-white/5 hover:text-cream"
                    @click="goBack"
                >
                    <Icon name="chevron-left" class="size-4" />
                    Retour
                </button>
            </header>

            <section class="flex flex-1 flex-col justify-center py-8">
                <p class="text-center text-sm font-semibold tracking-[0.28em] text-brand uppercase">Votre avis compte</p>
                <h1 class="mx-auto mt-4 max-w-2xl text-center text-3xl leading-tight font-semibold tracking-tight sm:text-5xl">
                    {{ question }}
                </h1>
                <p v-if="isCoolingDown" class="mx-auto mt-3 max-w-md text-center text-base text-cream/50">
                    Prochain avis dans {{ cooldownSeconds }} s
                </p>

                <div class="mt-10 flex flex-wrap items-end justify-center gap-4 sm:mt-12 sm:gap-8" :class="isCoolingDown ? 'pointer-events-none opacity-40' : ''">
                    <button
                        v-for="(rating, index) in ratings"
                        :key="rating.value"
                        type="button"
                        class="group relative flex min-h-44 w-36 flex-col items-center justify-end gap-4 p-2 sm:min-h-52 sm:w-44"
                        :style="{ animationDelay: `${index * 90}ms` }"
                        :aria-pressed="selected === rating.value"
                        :disabled="isCoolingDown"
                        @click="pick(rating.value)"
                    >
                        <span
                            class="face-orb relative grid size-32 place-items-center rounded-full transition duration-300 sm:size-40"
                            :class="[
                                selected === rating.value ? moods[rating.value]?.glow : 'hover:-translate-y-2 hover:scale-105',
                                selected === rating.value ? 'scale-110 -translate-y-1' : 'scale-100',
                                popping === rating.value ? 'face-pop' : '',
                                selected && selected !== rating.value ? 'opacity-35 grayscale' : 'opacity-100',
                            ]"
                        >
                            <span
                                class="absolute inset-0 rounded-full transition"
                                :class="
                                    selected === rating.value
                                        ? rating.value === 'very_satisfied'
                                            ? 'bg-brand'
                                            : rating.value === 'satisfied'
                                              ? 'bg-amber-300'
                                              : 'bg-rose-300'
                                        : 'bg-white/8 ring-1 ring-white/15 group-hover:bg-white/12'
                                "
                            />
                            <svg
                                class="relative size-20 sm:size-28"
                                viewBox="0 0 120 120"
                                fill="none"
                                aria-hidden="true"
                            >
                                <template v-if="rating.value === 'very_satisfied'">
                                    <circle cx="60" cy="60" r="54" :fill="selected === rating.value ? '#111' : '#92bc35'" />
                                    <circle cx="42" cy="48" r="7" :fill="selected === rating.value ? '#92bc35' : '#111'" class="eye-blink" />
                                    <circle cx="78" cy="48" r="7" :fill="selected === rating.value ? '#92bc35' : '#111'" class="eye-blink" />
                                    <path
                                        d="M36 68c8 18 40 18 48 0"
                                        :stroke="selected === rating.value ? '#92bc35' : '#111'"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        class="mouth-happy"
                                    />
                                    <circle cx="28" cy="62" r="8" fill="#f472b6" opacity="0.55" class="blush" />
                                    <circle cx="92" cy="62" r="8" fill="#f472b6" opacity="0.55" class="blush" />
                                </template>
                                <template v-else-if="rating.value === 'satisfied'">
                                    <circle cx="60" cy="60" r="54" :fill="selected === rating.value ? '#111' : '#fbbf24'" />
                                    <circle cx="42" cy="50" r="7" :fill="selected === rating.value ? '#fbbf24' : '#111'" class="eye-blink" />
                                    <circle cx="78" cy="50" r="7" :fill="selected === rating.value ? '#fbbf24' : '#111'" class="eye-blink" />
                                    <path
                                        d="M38 72c8 12 36 12 44 0"
                                        :stroke="selected === rating.value ? '#fbbf24' : '#111'"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                        class="mouth-happy"
                                    />
                                </template>
                                <template v-else>
                                    <circle cx="60" cy="60" r="54" :fill="selected === rating.value ? '#111' : '#fda4af'" />
                                    <path
                                        d="M34 44c4 8 12 8 16 0M70 44c4 8 12 8 16 0"
                                        :stroke="selected === rating.value ? '#fda4af' : '#111'"
                                        stroke-width="7"
                                        stroke-linecap="round"
                                    />
                                    <path
                                        d="M40 84c10-14 30-14 40 0"
                                        :stroke="selected === rating.value ? '#fda4af' : '#111'"
                                        stroke-width="8"
                                        stroke-linecap="round"
                                    />
                                    <path
                                        d="M86 78c2 8 6 14 12 16"
                                        :stroke="selected === rating.value ? '#fda4af' : '#111'"
                                        stroke-width="5"
                                        stroke-linecap="round"
                                        class="tear"
                                    />
                                </template>
                            </svg>
                        </span>
                        <span
                            class="rounded-full px-4 py-1.5 text-sm font-semibold transition"
                            :class="selected === rating.value ? moods[rating.value]?.chip : 'bg-white/8 text-cream/80'"
                        >
                            {{ rating.label }}
                        </span>
                    </button>
                </div>
            </section>
        </div>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showComment && !thanks"
                class="fixed inset-0 z-20 grid place-items-end bg-ink/55 px-4 py-4 backdrop-blur-xl sm:place-items-center sm:px-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="review-comment-title"
                @click.self="closeComment"
            >
                <div
                    class="comment-card relative w-full max-w-lg overflow-hidden rounded-[2rem] border border-white/20 bg-ink/45 p-6 text-cream shadow-2xl shadow-brand/20 backdrop-blur-2xl sm:p-8"
                >
                    <div class="pointer-events-none absolute -top-16 right-0 size-40 rounded-full bg-brand/25 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-20 -left-10 size-44 rounded-full bg-brand/10 blur-3xl" />

                    <div class="relative flex items-start justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <span
                                class="grid size-16 shrink-0 place-items-center rounded-full"
                                :class="
                                    selected === 'very_satisfied'
                                        ? 'bg-brand'
                                        : selected === 'satisfied'
                                          ? 'bg-amber-300'
                                          : 'bg-rose-300'
                                "
                            >
                                <svg class="size-10" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                                    <template v-if="selected === 'very_satisfied'">
                                        <circle cx="42" cy="48" r="7" fill="#111" />
                                        <circle cx="78" cy="48" r="7" fill="#111" />
                                        <path d="M36 68c8 18 40 18 48 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                    </template>
                                    <template v-else-if="selected === 'satisfied'">
                                        <circle cx="42" cy="50" r="7" fill="#111" />
                                        <circle cx="78" cy="50" r="7" fill="#111" />
                                        <path d="M38 72c8 12 36 12 44 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                    </template>
                                    <template v-else>
                                        <path d="M34 44c4 8 12 8 16 0M70 44c4 8 12 8 16 0" stroke="#111" stroke-width="7" stroke-linecap="round" />
                                        <path d="M40 84c10-14 30-14 40 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                                    </template>
                                </svg>
                            </span>
                            <div>
                                <p id="review-comment-title" class="text-2xl font-semibold tracking-tight">Un mot à ajouter ?</p>
                                <p v-if="mood" class="mt-1 text-sm text-cream/60">{{ mood.hint }}</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="grid size-10 shrink-0 place-items-center rounded-full border border-white/15 bg-white/5 text-cream/55 transition hover:bg-white/10 hover:text-cream"
                            aria-label="Fermer"
                            @click="closeComment"
                        >
                            <Icon name="x" class="size-5" />
                        </button>
                    </div>

                    <label class="relative mt-7 grid gap-2">
                        <span class="text-sm text-cream/45">Facultatif · max. 500 caractères</span>
                        <textarea
                            ref="commentInput"
                            v-model="comment"
                            rows="4"
                            maxlength="500"
                            class="w-full resize-none rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-base text-cream outline-none placeholder:text-cream/35 focus:border-brand/70 focus:bg-white/15"
                            placeholder="Une phrase, un détail, un merci…"
                        />
                    </label>

                    <p v-if="error" class="relative mt-3 text-sm text-rose-300">{{ error }}</p>

                    <button
                        type="button"
                        class="relative mt-6 inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand px-5 py-3.5 text-base font-semibold text-ink transition hover:scale-[1.02] hover:bg-brand/90 disabled:opacity-60"
                        :disabled="processing || !selected"
                        @click="submit()"
                    >
                        <Icon name="check" class="size-5" />
                        {{ processing ? 'Envoi…' : 'Envoyer mon avis' }}
                    </button>
                </div>
            </div>
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="thanks" class="fixed inset-0 z-30 grid place-items-center overflow-hidden bg-ink/90 px-6 backdrop-blur-md" role="status" aria-live="assertive">
                <span
                    v-for="sparkle in sparkles"
                    :key="sparkle.id"
                    class="pointer-events-none absolute top-0 rounded-full opacity-80 sparkle"
                    :class="sparkle.tone"
                    :style="{ left: sparkle.left, width: sparkle.size, height: sparkle.size, animationDelay: sparkle.delay }"
                />
                <div class="thanks-card relative w-full max-w-xl rounded-[2.5rem] bg-brand px-10 py-14 text-center text-ink shadow-2xl sm:px-16">
                    <span class="mx-auto grid size-28 place-items-center rounded-full bg-ink text-brand">
                        <svg class="size-16" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                            <circle cx="60" cy="60" r="54" fill="currentColor" />
                            <circle cx="42" cy="48" r="7" fill="#111" />
                            <circle cx="78" cy="48" r="7" fill="#111" />
                            <path d="M36 68c8 18 40 18 48 0" stroke="#111" stroke-width="8" stroke-linecap="round" />
                        </svg>
                    </span>
                    <p class="mt-8 text-lg font-semibold tracking-[0.2em] uppercase opacity-70">Merci</p>
                    <p class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">{{ mood?.thanks ?? 'Votre avis est enregistré' }}</p>
                </div>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
.face-orb {
    animation: face-in 0.55s cubic-bezier(0.2, 0.9, 0.3, 1.15) both;
}

.face-pop {
    animation: face-pop 0.45s cubic-bezier(0.2, 0.9, 0.3, 1.25);
}

.eye-blink {
    transform-origin: center;
    animation: blink 3.8s ease-in-out infinite;
}

.mouth-happy {
    transform-origin: center;
    animation: smile-pulse 2.4s ease-in-out infinite;
}

.blush {
    animation: blush 2.8s ease-in-out infinite;
}

.tear {
    animation: tear-drop 1.8s ease-in-out infinite;
}

.comment-card {
    animation: greeting-pop 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.15);
}

.thanks-card {
    animation: greeting-pop 0.5s cubic-bezier(0.2, 0.9, 0.3, 1.2);
}

.sparkle {
    animation: fall 2.4s linear infinite;
}

@keyframes face-in {
    from {
        opacity: 0;
        transform: translateY(1.25rem) scale(0.85);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes face-pop {
    0% {
        transform: scale(1);
    }

    40% {
        transform: scale(1.18) rotate(-4deg);
    }

    70% {
        transform: scale(0.96) rotate(3deg);
    }

    100% {
        transform: scale(1.1);
    }
}

@keyframes blink {
    0%,
    46%,
    50%,
    100% {
        transform: scaleY(1);
    }

    48% {
        transform: scaleY(0.15);
    }
}

@keyframes smile-pulse {
    0%,
    100% {
        transform: scaleX(1);
    }

    50% {
        transform: scaleX(1.06);
    }
}

@keyframes blush {
    0%,
    100% {
        opacity: 0.35;
    }

    50% {
        opacity: 0.7;
    }
}

@keyframes tear-drop {
    0%,
    100% {
        opacity: 0.35;
        transform: translateY(0);
    }

    50% {
        opacity: 0.9;
        transform: translateY(4px);
    }
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

@keyframes fall {
    0% {
        transform: translateY(-10vh) rotate(0deg);
        opacity: 0;
    }

    15% {
        opacity: 1;
    }

    100% {
        transform: translateY(110vh) rotate(220deg);
        opacity: 0;
    }
}
</style>
