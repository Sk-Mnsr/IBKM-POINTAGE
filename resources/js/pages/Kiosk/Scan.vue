<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';

defineProps<{
    kioskName: string;
    shopName: string | null;
    punchUrl: string;
    reviewUrl: string;
}>();
</script>

<template>
    <Head :title="`Scan · ${shopName ?? kioskName}`" />

    <main class="relative min-h-screen overflow-hidden bg-ink text-cream">
        <div class="pointer-events-none absolute -top-48 -left-40 size-[38rem] rounded-full bg-brand/20 blur-3xl" />
        <div class="pointer-events-none absolute -right-40 -bottom-56 size-[42rem] rounded-full bg-brand/10 blur-3xl" />

        <div class="relative mx-auto flex min-h-screen w-full max-w-lg flex-col justify-center gap-10 px-6 py-10">
            <div class="grid gap-2 text-center">
                <AppLogo variant="light" class="mx-auto w-44" />
                <p v-if="shopName" class="text-sm font-medium tracking-[0.2em] text-brand uppercase">{{ shopName }}</p>
                <h1 class="mt-4 text-3xl font-semibold tracking-tight">Que souhaitez-vous faire ?</h1>
                <p class="text-sm text-cream/55">Vous avez scanné la borne {{ kioskName }}.</p>
            </div>

            <div class="grid gap-3">
                <Link
                    :href="punchUrl"
                    class="flex items-center gap-4 rounded-[1.75rem] border border-white/10 bg-white/[0.06] px-5 py-5 transition hover:bg-white/10"
                >
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-brand text-ink">
                        <Icon name="qr" class="size-7" />
                    </span>
                    <span class="min-w-0 text-left">
                        <span class="block text-lg font-semibold">Pointer</span>
                        <span class="block text-sm text-cream/55">Personnel · arrivée, pause ou départ</span>
                    </span>
                    <Icon name="chevron-right" class="ml-auto size-5 text-cream/35" />
                </Link>

                <Link
                    :href="reviewUrl"
                    class="flex items-center gap-4 rounded-[1.75rem] border border-brand/35 bg-brand/15 px-5 py-5 transition hover:bg-brand/25"
                >
                    <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-brand text-ink">
                        <Icon name="star" class="size-7" />
                    </span>
                    <span class="min-w-0 text-left">
                        <span class="block text-lg font-semibold">Avis client</span>
                        <span class="block text-sm text-cream/55">Noter votre prise en charge</span>
                    </span>
                    <Icon name="chevron-right" class="ml-auto size-5 text-cream/35" />
                </Link>
            </div>
        </div>
    </main>
</template>
