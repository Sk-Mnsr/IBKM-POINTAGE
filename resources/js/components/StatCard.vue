<script setup lang="ts">
import Icon from '@/components/Icon.vue';

type StatTone = 'ink' | 'emerald' | 'amber' | 'rose' | 'sky' | 'brand';

withDefaults(defineProps<{ label: string; value: string | number; icon: string; tone?: StatTone; hint?: string | null }>(), {
    tone: 'ink',
    hint: null,
});

const iconTones: Record<StatTone, string> = {
    ink: 'bg-ink text-cream',
    emerald: 'bg-brand/15 text-brand-dark',
    amber: 'bg-amber-100 text-amber-800',
    rose: 'bg-rose-100 text-rose-800',
    sky: 'bg-sky-100 text-sky-800',
    brand: 'bg-brand/15 text-brand-dark',
};
</script>

<template>
    <article class="flex items-start justify-between gap-4 rounded-2xl border border-line bg-paper p-5">
        <div class="min-w-0">
            <p class="text-sm text-ink/60">{{ label }}</p>
            <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">{{ value }}</p>
            <p v-if="hint" class="mt-1 text-xs text-ink/50">{{ hint }}</p>
        </div>
        <span class="grid size-10 place-items-center rounded-xl" :class="iconTones[tone]">
            <Icon :name="icon" />
        </span>
    </article>
</template>
