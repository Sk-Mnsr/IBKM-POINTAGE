<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ value: number; total: number; size?: string }>(), { size: 'size-36' });

const percent = computed(() => (props.total > 0 ? Math.round((props.value / props.total) * 100) : 0));
</script>

<template>
    <div class="relative grid shrink-0 place-items-center" :class="size">
        <svg class="absolute inset-0 -rotate-90" viewBox="0 0 100 100" aria-hidden="true">
            <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="9" class="text-white/10" />
            <circle
                cx="50"
                cy="50"
                r="42"
                fill="none"
                stroke="currentColor"
                stroke-width="9"
                stroke-linecap="round"
                pathLength="100"
                stroke-dasharray="100"
                :stroke-dashoffset="100 - percent"
                class="text-brand transition-[stroke-dashoffset] duration-700 ease-out"
            />
        </svg>
        <div class="text-center">
            <p class="text-3xl font-semibold tracking-tight tabular-nums">{{ total > 0 ? `${percent}%` : '—' }}</p>
            <slot />
        </div>
    </div>
</template>
