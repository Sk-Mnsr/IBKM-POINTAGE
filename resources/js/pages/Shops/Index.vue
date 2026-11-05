<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import type { Shop } from '@/types';

const props = defineProps<{ shops: Shop[] }>();

const openCount = computed(() => props.shops.filter((shop) => shop.isActive).length);
const staffTotal = computed(() => props.shops.reduce((total, shop) => total + (shop.staffCount ?? 0), 0));
</script>

<template>
    <Head title="Boutiques" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Boutiques</h1>
            <p class="mt-1 text-sm text-ink/60">
                {{ openCount }} boutique(s) ouverte(s) · {{ staffTotal }} employé(s) actif(s) au total.
            </p>
        </div>
        <Link :href="route('shops.create')" class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-4 text-sm font-medium text-cream">
            <Icon name="plus" class="size-4" />
            Nouvelle boutique
        </Link>
    </div>

    <ul class="mt-6 grid gap-4 lg:grid-cols-2">
        <li v-for="shop in shops" :key="shop.id" class="flex flex-col gap-5 rounded-3xl border border-line bg-paper p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-2xl" :class="shop.isActive ? 'bg-brand/15 text-brand-dark' : 'bg-stone-100 text-ink/40'">
                        <Icon name="store" />
                    </span>
                    <div>
                        <p class="text-lg font-semibold">{{ shop.name }}</p>
                        <p class="text-sm text-ink/55">{{ shop.address ?? 'Adresse non renseignée' }}<template v-if="shop.phone"> · {{ shop.phone }}</template></p>
                    </div>
                </div>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="shop.isActive ? 'bg-brand/15 text-brand-dark' : 'bg-rose-50 text-rose-800'"
                >
                    <span class="size-1.5 rounded-full" :class="shop.isActive ? 'bg-brand' : 'bg-rose-500'" />
                    {{ shop.isActive ? 'Ouverte' : 'Fermée' }}
                </span>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-2xl bg-cream/70 px-4 py-3">
                    <dt class="text-xs text-ink/55">Personnel actif</dt>
                    <dd class="text-2xl font-semibold tabular-nums">{{ shop.staffCount ?? 0 }}</dd>
                </div>
                <div class="rounded-2xl bg-cream/70 px-4 py-3">
                    <dt class="text-xs text-ink/55">Gérant(s)</dt>
                    <dd class="truncate pt-1 font-medium">{{ shop.managers?.length ? shop.managers.map((manager) => manager.name).join(', ') : 'Aucun gérant' }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line pt-4 text-sm">
                <a
                    v-for="kiosk in shop.kiosks"
                    :key="kiosk.url"
                    :href="kiosk.url"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 font-medium text-brand-dark"
                >
                    <Icon name="qr" class="size-4" />
                    Borne {{ kiosk.name }}
                </a>
                <Link :href="route('shops.edit', shop.id)" class="ml-auto inline-flex items-center gap-1 text-ink/70 hover:text-ink">
                    <Icon name="pencil" class="size-4" />
                    Modifier
                </Link>
            </div>
        </li>
    </ul>

    <p v-if="shops.length === 0" class="mt-6 rounded-3xl border border-dashed border-line bg-paper p-10 text-center text-sm text-ink/55">
        Aucune boutique pour le moment.
    </p>
</template>
