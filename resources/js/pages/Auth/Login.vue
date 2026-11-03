<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';

const form = useForm({
    identifier: '',
    password: '',
    remember: true,
});

const submit = (): void => {
    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />

    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-12">
        <Link :href="route('home')" class="mb-8 text-sm text-brand-dark">Retour</Link>
        <AppLogo class="mb-8 w-48" />
        <h1 class="text-3xl font-semibold">Connexion</h1>
        <p class="mt-2 text-ink/70">E-mail du gérant, ou téléphone du personnel.</p>

        <form class="mt-8 grid gap-4" @submit.prevent="submit">
            <label class="grid gap-1.5 text-sm font-medium">
                <span>E-mail ou téléphone</span>
                <input v-model="form.identifier" name="identifier" autocomplete="username" required class="h-12 rounded-2xl border border-line bg-paper px-4 outline-none focus:border-brand">
                <span v-if="form.errors.identifier" class="font-normal text-rose-800">{{ form.errors.identifier }}</span>
            </label>
            <label class="grid gap-1.5 text-sm font-medium">
                <span>Mot de passe</span>
                <input v-model="form.password" type="password" name="password" autocomplete="current-password" required class="h-12 rounded-2xl border border-line bg-paper px-4 outline-none focus:border-brand">
                <span v-if="form.errors.password" class="font-normal text-rose-800">{{ form.errors.password }}</span>
            </label>
            <label class="flex items-center gap-3 text-sm">
                <input v-model="form.remember" type="checkbox" class="size-5 accent-ink">
                Rester connecté sur cet appareil
            </label>
            <button type="submit" :disabled="form.processing" class="mt-2 h-12 rounded-full bg-ink font-medium text-cream disabled:opacity-60">Entrer</button>
        </form>
    </main>
</template>
