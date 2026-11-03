<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import type { ReportMonth } from '@/types';

const props = withDefaults(defineProps<{ month: ReportMonth; routeName: string; params?: Record<string, unknown> }>(), {
    params: () => ({}),
});

const hrefFor = (value: string): string => route(props.routeName, { ...props.params, mois: value });
</script>

<template>
    <div class="inline-flex items-center rounded-full border border-line bg-paper p-1">
        <Link :href="hrefFor(month.previous)" preserve-scroll class="grid size-9 place-items-center rounded-full hover:bg-cream" aria-label="Mois précédent">
            <Icon name="chevron-left" class="size-4" />
        </Link>
        <span class="min-w-36 px-3 text-center text-sm font-medium">{{ month.label }}</span>
        <Link v-if="month.next" :href="hrefFor(month.next)" preserve-scroll class="grid size-9 place-items-center rounded-full hover:bg-cream" aria-label="Mois suivant">
            <Icon name="chevron-right" class="size-4" />
        </Link>
        <span v-else class="grid size-9 place-items-center text-ink/25">
            <Icon name="chevron-right" class="size-4" />
        </span>
    </div>
</template>
