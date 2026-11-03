<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';
import qrcode from '@/vendor/qrcode.js';

const page = usePage();

const user = computed(() => page.props.auth.user);
const resumeUrl = computed(() => (user.value?.role === 'admin' ? route('dashboard') : route('punch.create')));

const features = [
    { icon: 'qr', title: 'QR code sécurisé', detail: 'Renouvelé toutes les 30 secondes sur la tablette de l’accueil.' },
    { icon: 'wifi-off', title: 'Fonctionne hors ligne', detail: 'Les pointages sont gardés puis envoyés dès le retour du réseau.' },
    { icon: 'chart', title: 'Rapports automatiques', detail: 'Retards, heures, absences et exports Excel ou PDF pour la paie.' },
];

const qrMarkup = computed(() => {
    const code = qrcode(0, 'L');
    code.addData('King Maelick · Pointage');
    code.make();

    return code.createSvgTag({ cellSize: 4, margin: 0, scalable: true, alt: '' });
});

const year = new Date().getFullYear();
</script>

<template>
    <Head :title="page.props.app.instituteName" />

    <main class="grid min-h-screen lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <section class="relative flex flex-col justify-between gap-12 overflow-hidden bg-ink px-6 py-8 text-cream sm:px-12 lg:py-12">
            <div class="pointer-events-none absolute -top-40 -left-32 size-[34rem] rounded-full bg-brand/20 blur-3xl" />
            <div class="pointer-events-none absolute -right-40 -bottom-48 size-[30rem] rounded-full bg-brand/10 blur-3xl" />

            <AppLogo variant="light" class="relative w-40 sm:w-48" />

            <div class="relative grid gap-10 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                <div>
                    <p class="text-sm font-semibold tracking-[0.25em] text-brand uppercase">Institut de beauté</p>
                    <h1 class="mt-4 max-w-xl text-4xl leading-[1.1] font-semibold tracking-tight sm:text-5xl">
                        Le pointage du personnel, simple et sécurisé.
                    </h1>
                    <p class="mt-5 max-w-lg text-lg text-cream/65">
                        Un code sur la tablette de l’accueil, un scan avec le téléphone : arrivées, pauses et départs sont enregistrés en un geste.
                    </p>

                    <ul class="mt-10 grid max-w-lg gap-4">
                        <li v-for="feature in features" :key="feature.title" class="flex gap-4">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand/15 text-brand">
                                <Icon :name="feature.icon" />
                            </span>
                            <span>
                                <span class="block font-semibold">{{ feature.title }}</span>
                                <span class="block text-sm text-cream/55">{{ feature.detail }}</span>
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="relative mx-auto hidden h-80 w-72 sm:block" aria-hidden="true">
                    <div class="absolute inset-y-0 left-0 w-60 rounded-[2rem] border border-white/10 bg-white/[0.06] p-4 shadow-2xl backdrop-blur">
                        <div class="flex items-center justify-between text-[10px] text-cream/50">
                            <span>Borne Accueil</span>
                            <span class="flex items-center gap-1"><span class="size-1.5 rounded-full bg-brand" />connectée</span>
                        </div>
                        <div class="mt-4 grid place-items-center">
                            <div class="relative grid size-44 place-items-center">
                                <svg class="absolute inset-0 -rotate-90" viewBox="0 0 100 100">
                                    <circle cx="50" cy="50" r="48" fill="none" stroke="currentColor" stroke-width="1.5" class="text-white/10" />
                                    <circle cx="50" cy="50" r="48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" pathLength="100" stroke-dasharray="100" stroke-dashoffset="35" class="text-brand" />
                                </svg>
                                <div class="w-28 rounded-xl bg-white p-2" v-html="qrMarkup" />
                            </div>
                        </div>
                        <p class="mt-3 text-center text-[11px] text-cream/50">Nouveau code dans 19 s</p>
                    </div>

                    <div class="absolute right-0 bottom-0 w-32 rounded-[1.6rem] border-4 border-ink bg-cream p-2.5 text-ink shadow-2xl ring-1 ring-white/10">
                        <div class="mx-auto mb-2 h-1 w-8 rounded-full bg-ink/15" />
                        <div class="rounded-xl bg-brand-dark p-2 text-white">
                            <span class="grid size-6 place-items-center rounded-full bg-white/20">
                                <Icon name="check" class="size-3.5" />
                            </span>
                            <p class="mt-1.5 text-[10px] font-semibold">Pointage validé</p>
                            <p class="text-[9px] text-white/75">Bonjour Awa · 08:52</p>
                        </div>
                        <div class="mt-2 grid gap-1">
                            <span class="h-1.5 rounded-full bg-ink/10" />
                            <span class="h-1.5 w-2/3 rounded-full bg-ink/10" />
                        </div>
                        <div class="mt-2 rounded-lg bg-brand py-1.5 text-center text-[9px] font-semibold">Partir en pause</div>
                    </div>
                </div>
            </div>

            <p class="relative text-xs text-cream/35">© {{ year }} {{ page.props.app.instituteName }}</p>
        </section>

        <section class="flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
            <div class="mx-auto w-full max-w-md">
                <h2 class="text-3xl font-semibold tracking-tight">Accéder à votre espace</h2>
                <p class="mt-2 text-ink/60">Choisissez votre profil pour vous connecter.</p>

                <Link
                    v-if="user"
                    :href="resumeUrl"
                    class="group mt-8 flex items-center gap-4 rounded-3xl border-2 border-brand bg-brand/10 p-5 transition hover:bg-brand/15"
                >
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand text-sm font-semibold text-ink">{{ user.initials }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-brand-dark">Vous êtes connecté</span>
                        <span class="block truncate text-lg font-semibold">Continuer en tant que {{ user.name }}</span>
                    </span>
                    <Icon name="chevron-right" class="size-5 text-brand-dark transition group-hover:translate-x-1" />
                </Link>

                <div class="mt-8 grid gap-4">
                    <Link
                        :href="route('login')"
                        class="group flex items-center gap-5 rounded-3xl bg-ink p-6 text-cream shadow-xl shadow-ink/10 transition hover:-translate-y-0.5 hover:shadow-2xl"
                    >
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-brand text-ink">
                            <Icon name="dashboard" class="size-7" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm text-brand">Gérant</span>
                            <span class="block text-xl font-semibold">Tableau de bord</span>
                            <span class="mt-1 block text-sm text-cream/60">Présences en direct, rapports, congés et exports.</span>
                        </span>
                        <Icon name="chevron-right" class="size-5 text-cream/40 transition group-hover:translate-x-1 group-hover:text-cream" />
                    </Link>

                    <Link
                        :href="route('login')"
                        class="group flex items-center gap-5 rounded-3xl border border-line bg-paper p-6 transition hover:-translate-y-0.5 hover:border-ink/20 hover:shadow-xl hover:shadow-ink/5"
                    >
                        <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-brand/15 text-brand-dark">
                            <Icon name="smartphone" class="size-7" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm text-brand-dark">Personnel</span>
                            <span class="block text-xl font-semibold">Mon pointage</span>
                            <span class="mt-1 block text-sm text-ink/60">Scanner le QR code, voir mon historique et mes congés.</span>
                        </span>
                        <Icon name="chevron-right" class="size-5 text-ink/30 transition group-hover:translate-x-1 group-hover:text-ink" />
                    </Link>
                </div>

                <div class="mt-8 flex items-start gap-3 rounded-2xl bg-cream px-4 py-3 text-sm text-ink/60">
                    <Icon name="shield" class="mt-0.5 size-4 shrink-0 text-brand-dark" />
                    <p>Le personnel se connecte avec son numéro de téléphone. Le mot de passe est remis par le gérant.</p>
                </div>
            </div>
        </section>
    </main>
</template>
