<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';

defineProps<{ kioskName: string }>();
</script>

<template>
    <Head :title="`Borne · ${kioskName}`" />

    <main class="relative grid min-h-screen place-items-center overflow-hidden bg-ink px-6 text-cream">
        <div class="pointer-events-none absolute -top-48 -left-40 size-[38rem] rounded-full bg-brand/15 blur-3xl" />

        <div class="relative grid max-w-lg justify-items-center gap-6 text-center">
            <AppLogo variant="light" class="w-44" />
            <span class="grid size-16 place-items-center rounded-2xl bg-white/10 text-brand">
                <Icon name="shield" class="size-8" />
            </span>
            <div>
                <h1 class="text-3xl font-semibold tracking-tight">Borne {{ kioskName }} non appairée</h1>
                <p class="mt-3 text-cream/65">
                    Pour des raisons de sécurité, seule une tablette autorisée par le gérant peut afficher les codes de pointage.
                    Connectez-vous une fois avec le compte gérant sur cette tablette, puis touchez « Appairer cette tablette ».
                </p>
            </div>
            <Link :href="route('login')" class="inline-flex h-12 items-center gap-2 rounded-full bg-brand px-6 font-semibold text-ink hover:bg-brand/90">
                <Icon name="login" class="size-4" />
                Se connecter en tant que gérant
            </Link>
        </div>
    </main>
</template>
