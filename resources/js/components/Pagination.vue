<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/components/Icon.vue';
import type { PaginationLink } from '@/types';

defineProps<{ links: PaginationLink[] }>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center gap-1" aria-label="Pagination">
        <template v-for="(link, index) in links" :key="index">
            <component
                :is="link.url ? Link : 'span'"
                :href="link.url ?? undefined"
                preserve-scroll
                class="grid h-9 min-w-9 place-items-center rounded-xl px-3 text-sm"
                :class="[
                    link.active ? 'bg-ink font-medium text-cream' : 'border border-line bg-paper',
                    link.url ? 'hover:bg-cream' : 'text-ink/30',
                ]"
            >
                <Icon v-if="index === 0" name="chevron-left" class="size-4" />
                <Icon v-else-if="index === links.length - 1" name="chevron-right" class="size-4" />
                <span v-else>{{ link.label }}</span>
            </component>
        </template>
    </nav>
</template>
