<script setup lang="ts">
import type { Tone } from '@/types';

withDefaults(defineProps<{ tone?: Tone; label: string }>(), { tone: 'neutral' });

const tones: Record<Tone, string> = {
    emerald: 'bg-brand/15 text-brand-dark ring-brand/40',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
    rose: 'bg-rose-50 text-rose-800 ring-rose-200',
    sky: 'bg-sky-50 text-sky-800 ring-sky-200',
    stone: 'bg-stone-100 text-stone-700 ring-stone-200',
    neutral: 'bg-white text-ink/60 ring-line',
};

const dots: Record<Tone, string> = {
    emerald: 'bg-brand',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    sky: 'bg-sky-500',
    stone: 'bg-stone-400',
    neutral: 'bg-ink/30',
};
</script>

<template>
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset" :class="tones[tone]">
        <span class="size-1.5 rounded-full" :class="dots[tone]" />
        {{ label }}
    </span>
</template>
