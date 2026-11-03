<script setup lang="ts">
withDefaults(defineProps<{ minWidth?: string }>(), { minWidth: '40rem' });
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-line bg-paper">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap" :style="{ minWidth }">
                <thead class="bg-cream/60 text-xs tracking-wide text-ink/55 uppercase">
                    <slot name="head" />
                </thead>
                <tbody class="divide-y divide-line">
                    <slot />
                </tbody>
                <slot name="foot" />
            </table>
        </div>
    </div>
</template>
