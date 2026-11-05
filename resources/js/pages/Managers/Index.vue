<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import type { Manager } from '@/types';

defineProps<{ managers: Manager[] }>();
</script>

<template>
    <Head title="Gérants" />

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">Gérants</h1>
            <p class="mt-1 text-sm text-ink/60">Un gérant ne voit que les boutiques qui lui sont confiées. Le super administrateur voit tout.</p>
        </div>
        <Link :href="route('managers.create')" class="inline-flex h-11 items-center gap-2 rounded-full bg-ink px-4 text-sm font-medium text-cream">
            <Icon name="plus" class="size-4" />
            Nouveau gérant
        </Link>
    </div>

    <ul class="mt-6 grid gap-3">
        <li
            v-for="manager in managers"
            :key="manager.id"
            class="flex flex-wrap items-center gap-4 rounded-3xl border border-line bg-paper px-5 py-4"
            :class="{ 'opacity-60': !manager.isActive }"
        >
            <span
                class="grid size-11 place-items-center rounded-2xl text-sm font-semibold"
                :class="manager.role === 'super_admin' ? 'bg-ink text-brand' : 'bg-brand/15 text-brand-dark'"
            >
                {{ manager.initials }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2 font-semibold">
                    {{ manager.name }}
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="manager.role === 'super_admin' ? 'bg-ink text-cream' : 'bg-cream text-ink/70'"
                    >
                        {{ manager.roleLabel }}
                    </span>
                    <span v-if="!manager.isActive" class="rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-800">Désactivé</span>
                </p>
                <p class="truncate text-sm text-ink/55">{{ manager.phone }}<template v-if="manager.email"> · {{ manager.email }}</template></p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <span v-if="manager.role === 'super_admin'" class="inline-flex items-center gap-1 rounded-full bg-brand/15 px-3 py-1 text-xs font-medium text-brand-dark">
                    <Icon name="store" class="size-3.5" />
                    Toutes les boutiques
                </span>
                <template v-else>
                    <span
                        v-for="shop in manager.shops"
                        :key="shop"
                        class="inline-flex items-center gap-1 rounded-full bg-cream px-3 py-1 text-xs font-medium text-ink/70"
                    >
                        <Icon name="store" class="size-3.5" />
                        {{ shop }}
                    </span>
                    <span v-if="!manager.shops?.length" class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-900">Aucune boutique</span>
                </template>
            </div>
            <Link :href="route('managers.edit', manager.id)" class="inline-flex items-center gap-1 text-sm text-ink/70 hover:text-ink">
                <Icon name="pencil" class="size-4" />
                Modifier
            </Link>
        </li>
    </ul>
</template>
