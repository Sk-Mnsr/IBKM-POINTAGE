<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import type { Department, ShopOption } from '@/types';

const props = defineProps<{ department: Department | null; shops: ShopOption[]; defaultShopId: number | null }>();

const form = useForm({
    shop_id: props.department?.shopId ?? props.defaultShopId ?? ('' as number | ''),
    name: props.department?.name ?? '',
});

const submit = (): void => {
    if (props.department) {
        form.put(route('departments.update', props.department.id));

        return;
    }

    form.post(route('departments.store'));
};
</script>

<template>
    <Head :title="department ? department.name : 'Nouveau département'" />

    <Link :href="route('departments.index')" class="inline-flex items-center gap-1 text-sm text-brand-dark">
        <Icon name="chevron-left" class="size-4" />
        Départements
    </Link>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ department ? department.name : 'Nouveau département' }}</h1>

    <p v-if="department?.shop" class="mt-1 flex items-center gap-1.5 text-sm text-ink/55">
        <Icon name="store" class="size-4" />
        {{ department.shop }}
    </p>

    <form class="mt-6 grid max-w-md gap-4 rounded-2xl border border-line bg-paper p-6" @submit.prevent="submit">
        <label v-if="!department && shops.length > 1" class="grid gap-1.5 text-sm font-medium">
            <span>Boutique</span>
            <select v-model="form.shop_id" required class="h-12 rounded-2xl border border-line bg-white px-4 outline-none focus:border-brand">
                <option v-for="shop in shops" :key="shop.id" :value="shop.id">{{ shop.name }}</option>
            </select>
            <span v-if="form.errors.shop_id" class="font-normal text-rose-800">{{ form.errors.shop_id }}</span>
        </label>
        <label class="grid gap-1.5 text-sm font-medium">
            <span>Nom</span>
            <input v-model="form.name" required class="h-12 rounded-2xl border border-line bg-white px-4 outline-none focus:border-brand">
            <span v-if="form.errors.name" class="font-normal text-rose-800">{{ form.errors.name }}</span>
        </label>
        <button type="submit" :disabled="form.processing" class="inline-flex h-12 items-center justify-center gap-2 rounded-full bg-ink font-medium text-cream disabled:opacity-60">
            <Icon name="check" class="size-4" />
            Enregistrer
        </button>
    </form>
</template>
