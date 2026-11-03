<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'dark' | 'light' }>(), { variant: 'dark' });
</script>

<template>
    <img
        :src="variant === 'light' ? '/images/logo-king-maelick-light.png' : '/images/logo-king-maelick.png'"
        alt="King Maelick"
        width="480"
        height="255"
        class="h-auto"
    >
</template>
