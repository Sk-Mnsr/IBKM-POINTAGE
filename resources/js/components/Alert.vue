<script setup lang="ts">
import Icon from '@/components/Icon.vue';

withDefaults(defineProps<{ tone?: 'success' | 'error' }>(), { tone: 'success' });
</script>

<template>
    <div
        class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm"
        :class="tone === 'success' ? 'border-brand/40 bg-brand/10 text-brand-dark' : 'border-rose-200 bg-rose-50 text-rose-900'"
        role="status"
    >
        <Icon :name="tone === 'success' ? 'check' : 'alert'" />
        <slot />
    </div>
</template>
