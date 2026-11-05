<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import Alert from '@/components/Alert.vue';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';
import ShopSwitcher from '@/components/ShopSwitcher.vue';
import { isCurrentPath } from '@/lib/format';

const page = usePage();
const menuOpen = ref(false);

const isSuperAdmin = computed(() => page.props.auth.user?.role === 'super_admin');
const shops = computed(() => page.props.shopContext?.shops ?? []);
const currentShopId = computed(() => page.props.shopContext?.current?.id ?? null);

const navigation = computed(() =>
    [
        { route: 'dashboard', label: 'Tableau de bord', icon: 'dashboard', badge: 0 },
        { route: 'punches.index', label: 'Pointages', icon: 'history', badge: 0 },
        { route: 'reports.index', label: 'Rapports', icon: 'chart', badge: 0 },
        { route: 'leaves.index', label: 'Congés', icon: 'plane', badge: page.props.pendingLeaveCount },
        { route: 'employees.index', label: 'Employés', icon: 'users', badge: 0 },
        { route: 'departments.index', label: 'Départements', icon: 'building', badge: 0 },
        ...(isSuperAdmin.value
            ? [
                  { route: 'shops.index', label: 'Boutiques', icon: 'store', badge: 0 },
                  { route: 'managers.index', label: 'Gérants', icon: 'user-cog', badge: 0 },
              ]
            : []),
    ].map((item) => {
        const href = route(item.route);

        return { ...item, href, active: isCurrentPath(page.url, href) };
    }),
);

watch(() => page.url, () => {
    menuOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
        <aside class="sticky top-0 hidden h-screen flex-col bg-ink px-4 py-6 text-cream lg:flex">
            <Link :href="route('dashboard')" class="grid gap-1 px-2">
                <AppLogo variant="light" class="w-36" />
                <span class="pl-1 text-[11px] font-medium tracking-[0.2em] text-cream/50 uppercase">Pointage</span>
            </Link>

            <div class="mt-8 px-1">
                <p class="mb-1.5 flex items-center gap-2 px-1 text-[11px] font-medium tracking-[0.15em] text-cream/45 uppercase">
                    <Icon name="store" class="size-3.5" />
                    Boutique
                </p>
                <ShopSwitcher v-if="shops.length > 1" :shops="shops" :current-id="currentShopId" />
                <p v-else class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-cream">
                    {{ shops[0]?.name ?? 'Aucune boutique' }}
                </p>
            </div>

            <nav class="mt-6 grid gap-1 text-sm">
                <Link
                    v-for="item in navigation"
                    :key="item.route"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition"
                    :class="item.active ? 'bg-brand font-medium text-ink' : 'text-cream/70 hover:bg-white/5 hover:text-cream'"
                >
                    <Icon :name="item.icon" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.badge > 0" class="rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-ink">{{ item.badge }}</span>
                </Link>
            </nav>

            <div class="mt-auto grid gap-3 border-t border-white/10 pt-5">
                <div class="flex items-center gap-3 px-2">
                    <span class="grid size-9 place-items-center rounded-full bg-white/10 text-xs font-semibold">{{ page.props.auth.user?.initials }}</span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ page.props.auth.user?.name }}</span>
                        <span class="block text-xs text-cream/50">{{ page.props.auth.user?.roleLabel }}</span>
                    </span>
                </div>
                <div class="grid gap-1">
                    <Link :href="route('password.edit')" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-cream/70 hover:bg-white/5 hover:text-cream">
                        <Icon name="shield" />
                        Mot de passe
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-cream/70 hover:bg-white/5 hover:text-cream">
                        <Icon name="logout" />
                        Se déconnecter
                    </Link>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="bg-ink text-cream lg:hidden">
                <button type="button" class="flex w-full items-center justify-between px-4 py-4" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">
                    <span class="flex items-center gap-3">
                        <AppLogo variant="light" class="w-24" />
                        <span class="text-[11px] font-medium tracking-[0.2em] text-cream/50 uppercase">Pointage</span>
                    </span>
                    <Icon :name="menuOpen ? 'x' : 'menu'" />
                </button>
                <nav v-show="menuOpen" class="grid gap-1 px-4 pb-4 text-sm">
                    <ShopSwitcher v-if="shops.length > 1" class="mb-2" :shops="shops" :current-id="currentShopId" />
                    <Link
                        v-for="item in navigation"
                        :key="item.route"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5"
                        :class="item.active ? 'bg-brand font-medium text-ink' : 'text-cream/75'"
                    >
                        <Icon :name="item.icon" />
                        <span class="flex-1">{{ item.label }}</span>
                        <span v-if="item.badge > 0" class="rounded-full bg-brand px-2 py-0.5 text-xs font-semibold text-ink">{{ item.badge }}</span>
                    </Link>
                    <Link :href="route('password.edit')" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-cream/75">
                        <Icon name="shield" />
                        Mot de passe
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-cream/75">
                        <Icon name="logout" />
                        Se déconnecter
                    </Link>
                </nav>
            </header>

            <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
                <Alert v-if="page.props.flash.status" class="mb-6">{{ page.props.flash.status }}</Alert>
                <Alert v-if="page.props.errors.leave" tone="error" class="mb-6">{{ page.props.errors.leave }}</Alert>
                <slot />
            </main>
        </div>
    </div>
</template>
