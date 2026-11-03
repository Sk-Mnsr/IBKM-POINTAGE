<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';
import { isCurrentPath } from '@/lib/format';

const page = usePage();

const navigation = computed(() =>
    [
        { route: 'punch.create', label: 'Pointer', icon: 'scan' },
        { route: 'staff.history', label: 'Historique', icon: 'history' },
        { route: 'staff.schedule', label: 'Planning', icon: 'calendar' },
        { route: 'staff.leaves.index', label: 'Congés', icon: 'plane' },
    ].map((item) => {
        const href = route(item.route);

        return { ...item, href, active: isCurrentPath(page.url, href) };
    }),
);
</script>

<template>
    <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col pb-24 sm:pb-8">
        <header class="flex items-center justify-between gap-4 px-4 pt-5 pb-4 sm:px-6">
            <AppLogo class="w-24" />
            <div class="flex items-center gap-3">
                <div class="text-right leading-tight">
                    <p class="text-sm font-semibold">{{ page.props.auth.user?.name }}</p>
                    <p class="text-xs text-ink/55">{{ page.props.auth.user?.department ?? 'Personnel' }}</p>
                </div>
                <Link :href="route('password.edit')" class="grid size-10 place-items-center rounded-full border border-line bg-paper text-ink/70" aria-label="Changer mon mot de passe">
                    <Icon name="shield" />
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="grid size-10 place-items-center rounded-full border border-line bg-paper text-ink/70" aria-label="Se déconnecter">
                    <Icon name="logout" />
                </Link>
            </div>
        </header>

        <nav class="mx-4 mb-6 hidden grid-cols-4 gap-1 rounded-2xl border border-line bg-paper p-1 text-sm sm:mx-6 sm:grid">
            <Link
                v-for="item in navigation"
                :key="item.route"
                :href="item.href"
                class="flex items-center justify-center gap-2 rounded-xl px-3 py-2.5"
                :class="item.active ? 'bg-ink font-medium text-cream' : 'text-ink/70 hover:bg-cream'"
            >
                <Icon :name="item.icon" class="size-4" />
                {{ item.label }}
            </Link>
        </nav>

        <main class="flex-1 px-4 sm:px-6">
            <slot />
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-10 grid grid-cols-4 border-t border-line bg-paper/95 px-2 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] backdrop-blur sm:hidden">
            <Link
                v-for="item in navigation"
                :key="item.route"
                :href="item.href"
                class="flex flex-col items-center gap-1 rounded-xl py-1.5 text-[11px]"
                :class="item.active ? 'font-semibold text-ink' : 'text-ink/50'"
            >
                <Icon :name="item.icon" />
                {{ item.label }}
            </Link>
        </nav>
    </div>
</template>
