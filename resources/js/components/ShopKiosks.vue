<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import { confirmAction } from '@/lib/confirm';
import type { Shop } from '@/types';

type ShopKiosk = NonNullable<Shop['kiosks']>[number];

const props = defineProps<{ shopId: number; kiosks: ShopKiosk[] }>();

const editingId = ref<string | null>(null);
const editedName = ref('');
const editError = ref<string | null>(null);

const newKiosk = useForm({ name: '' });

const visitOptions = { preserveScroll: true, preserveState: true } as const;

const startEditing = (kiosk: ShopKiosk): void => {
    editingId.value = kiosk.id;
    editedName.value = kiosk.name;
    editError.value = null;
};

const update = (kiosk: ShopKiosk, data: { name: string; is_active: boolean }): void => {
    router.put(route('shops.kiosks.update', { shop: props.shopId, kiosk: kiosk.id }), data, {
        ...visitOptions,
        onSuccess: () => {
            editingId.value = null;
        },
        onError: (errors) => {
            editError.value = errors.name ?? null;
        },
    });
};

const rename = (kiosk: ShopKiosk): void => {
    update(kiosk, { name: editedName.value, is_active: kiosk.isActive });
};

const toggle = async (kiosk: ShopKiosk): Promise<void> => {
    if (kiosk.isActive) {
        const confirmed = await confirmAction({
            title: `Désactiver la borne ${kiosk.name} ?`,
            message: 'Son QR code ne fonctionnera plus. Les pointages déjà faits restent dans l’historique.',
            confirmLabel: 'Désactiver',
            danger: true,
        });

        if (!confirmed) {
            return;
        }
    }

    update(kiosk, { name: kiosk.name, is_active: !kiosk.isActive });
};

const create = (): void => {
    newKiosk.post(route('shops.kiosks.store', props.shopId), {
        ...visitOptions,
        onSuccess: () => newKiosk.reset(),
    });
};
</script>

<template>
    <section class="rounded-3xl border border-line bg-paper p-6">
        <div class="flex items-center gap-3">
            <span class="grid size-9 place-items-center rounded-xl bg-ink text-cream"><Icon name="qr" class="size-4" /></span>
            <div>
                <h2 class="font-semibold">Bornes de pointage</h2>
                <p class="text-xs text-ink/50">Une borne par tablette. Ouvrez-la sur la tablette, puis appairez-la.</p>
            </div>
        </div>

        <ul class="mt-4 grid gap-2">
            <li v-for="kiosk in kiosks" :key="kiosk.id" class="rounded-2xl bg-cream/70 px-4 py-3" :class="{ 'opacity-60': !kiosk.isActive }">
                <form v-if="editingId === kiosk.id" class="flex flex-wrap items-center gap-2" @submit.prevent="rename(kiosk)">
                    <input
                        v-model="editedName"
                        required
                        aria-label="Nom de la borne"
                        class="h-9 min-w-0 flex-1 rounded-xl border border-line bg-white px-3 text-sm outline-none focus:border-brand"
                    >
                    <button type="submit" class="h-9 rounded-full bg-ink px-4 text-xs font-medium text-cream">Enregistrer</button>
                    <button type="button" class="h-9 px-2 text-xs text-ink/55" @click="editingId = null">Annuler</button>
                    <span v-if="editError" class="w-full text-xs text-rose-700">{{ editError }}</span>
                </form>
                <div v-else class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="flex-1 text-sm font-medium">
                        Borne {{ kiosk.name }}
                        <span v-if="!kiosk.isActive" class="ml-1.5 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-800">Désactivée</span>
                    </span>
                    <a v-if="kiosk.isActive" :href="kiosk.url" target="_blank" class="inline-flex items-center gap-1 text-sm font-medium text-brand-dark">
                        <Icon name="maximize" class="size-4" />
                        Ouvrir
                    </a>
                    <button type="button" class="inline-flex items-center gap-1 text-sm text-ink/60 hover:text-ink" @click="startEditing(kiosk)">
                        <Icon name="pencil" class="size-4" />
                        Renommer
                    </button>
                    <button type="button" class="text-sm" :class="kiosk.isActive ? 'text-ink/45 hover:text-rose-700' : 'font-medium text-brand-dark'" @click="toggle(kiosk)">
                        {{ kiosk.isActive ? 'Désactiver' : 'Réactiver' }}
                    </button>
                </div>
            </li>
            <li v-if="kiosks.length === 0" class="rounded-2xl bg-cream/70 px-4 py-3 text-sm text-ink/55">Aucune borne.</li>
        </ul>

        <form class="mt-4 flex gap-2" @submit.prevent="create">
            <input
                v-model="newKiosk.name"
                required
                placeholder="Nouvelle borne, ex. Entrée arrière"
                aria-label="Nom de la nouvelle borne"
                class="h-10 min-w-0 flex-1 rounded-xl border border-line bg-white px-3 text-sm outline-none placeholder:text-ink/30 focus:border-brand"
            >
            <button
                type="submit"
                :disabled="newKiosk.processing"
                class="inline-flex h-10 items-center gap-1.5 rounded-full bg-ink px-4 text-sm font-medium text-cream disabled:opacity-60"
            >
                <Icon name="plus" class="size-4" />
                Ajouter
            </button>
        </form>
        <span v-if="newKiosk.errors.name" class="mt-1 block text-xs text-rose-700">{{ newKiosk.errors.name }}</span>
    </section>
</template>
