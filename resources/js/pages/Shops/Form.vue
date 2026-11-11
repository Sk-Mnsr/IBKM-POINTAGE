<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import ShopKiosks from '@/components/ShopKiosks.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import type { Shop } from '@/types';

const props = defineProps<{ shop: Shop | null; defaultDepartments: string[] }>();

const form = useForm({
    name: props.shop?.name ?? '',
    address: props.shop?.address ?? '',
    phone: props.shop?.phone ?? '',
    is_active: props.shop?.isActive ?? true,
});

const isClosing = computed(() => props.shop?.isActive === true && !form.is_active);
const staffCount = computed(() => props.shop?.staffCount ?? 0);

const formattedPhone = computed(() => form.phone.replace(/\s+/g, '').replace(/(\d{2})(?=\d)/g, '$1 ').trim());

const submit = (): void => {
    if (props.shop) {
        form.put(route('shops.update', props.shop.id), { preserveScroll: true });

        return;
    }

    form.post(route('shops.store'));
};

const inputClasses =
    'h-11 w-full rounded-xl border border-line bg-white px-3.5 text-sm outline-none transition placeholder:text-ink/30 focus:border-brand focus:ring-4 focus:ring-brand/15';
</script>

<template>
    <Head :title="shop ? shop.name : 'Nouvelle boutique'" />

    <form class="grid gap-6 pb-24" @submit.prevent="submit">
        <Link :href="route('shops.index')" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-brand-dark">
            <Icon name="chevron-left" class="size-4" />
            Boutiques
        </Link>

        <header class="relative overflow-hidden rounded-3xl bg-ink p-6 text-cream">
            <div class="pointer-events-none absolute -top-20 -right-16 size-56 rounded-full bg-brand/20 blur-3xl" />
            <div class="relative flex flex-wrap items-center justify-between gap-5">
                <div class="flex items-center gap-4">
                    <span class="grid size-16 place-items-center rounded-2xl" :class="form.is_active ? 'bg-brand text-ink' : 'bg-white/10 text-cream/50'">
                        <Icon name="store" class="size-7" />
                    </span>
                    <div>
                        <p class="text-sm text-cream/55">{{ shop ? 'Modifier la boutique' : 'Nouvelle boutique' }}</p>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ form.name || 'Nom de la boutique' }}</h1>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5"
                                :class="form.is_active ? 'bg-brand/20 text-brand' : 'bg-rose-500/20 text-rose-200'"
                            >
                                <span class="size-1.5 rounded-full" :class="form.is_active ? 'bg-brand' : 'bg-rose-300'" />
                                {{ form.is_active ? 'Ouverte' : 'Fermée' }}
                            </span>
                            <span v-if="form.address" class="rounded-full bg-white/10 px-2.5 py-0.5 text-cream/80">{{ form.address }}</span>
                            <span v-if="form.phone" class="rounded-full bg-white/10 px-2.5 py-0.5 text-cream/80 tabular-nums">{{ formattedPhone }}</span>
                        </div>
                    </div>
                </div>
                <dl v-if="shop" class="flex gap-6 text-right">
                    <div>
                        <dt class="text-xs text-cream/50">Personnel actif</dt>
                        <dd class="text-2xl font-semibold tabular-nums">{{ staffCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-cream/50">Gérant(s)</dt>
                        <dd class="text-2xl font-semibold tabular-nums">{{ shop.managers?.length ?? 0 }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-cream/50">Borne(s)</dt>
                        <dd class="text-2xl font-semibold tabular-nums">{{ shop.kioskCount ?? 0 }}</dd>
                    </div>
                </dl>
            </div>
        </header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
            <div class="grid content-start gap-6">
                <section class="rounded-3xl border border-line bg-paper p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-brand/15 text-brand-dark"><Icon name="store" class="size-4" /></span>
                        <div>
                            <h2 class="font-semibold">Informations</h2>
                            <p class="text-xs text-ink/50">Le nom apparaît sur la borne, dans les rapports et dans le sélecteur de boutique.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">
                            Nom de la boutique
                            <input v-model="form.name" required autocomplete="off" placeholder="Ex. King Maelick Almadies" :class="inputClasses">
                            <span v-if="form.errors.name" class="text-xs font-normal text-rose-700">{{ form.errors.name }}</span>
                        </label>
                        <label class="grid gap-1.5 text-sm font-medium">
                            <span>Adresse <span class="text-xs font-normal text-ink/45">facultatif</span></span>
                            <input v-model="form.address" autocomplete="off" placeholder="Quartier, rue…" :class="inputClasses">
                            <span v-if="form.errors.address" class="text-xs font-normal text-rose-700">{{ form.errors.address }}</span>
                        </label>
                        <label class="grid gap-1.5 text-sm font-medium">
                            <span>Téléphone <span class="text-xs font-normal text-ink/45">facultatif</span></span>
                            <input v-model="form.phone" inputmode="tel" autocomplete="off" placeholder="Ex. 33 800 00 00" :class="inputClasses">
                            <span v-if="form.errors.phone" class="text-xs font-normal text-rose-700">{{ form.errors.phone }}</span>
                        </label>
                    </div>
                </section>

                <section class="rounded-3xl border border-line bg-paper p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 place-items-center rounded-xl bg-amber-100 text-amber-800"><Icon name="shield" class="size-4" /></span>
                            <div>
                                <h2 class="font-semibold">Boutique ouverte</h2>
                                <p class="text-xs text-ink/50">Une boutique fermée n’affiche plus de QR code et disparaît pour ses gérants.</p>
                            </div>
                        </div>
                        <ToggleSwitch v-model="form.is_active" label="Boutique ouverte" />
                    </div>
                    <p v-if="isClosing" class="mt-4 flex items-start gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <Icon name="alert" class="mt-0.5 size-4 shrink-0" />
                        <span>
                            En fermant cette boutique, <strong>{{ staffCount }} employé(s)</strong> ne pourront plus pointer et ses gérants n’y auront plus accès.
                            L’historique et les rapports restent conservés.
                        </span>
                    </p>
                </section>
            </div>

            <div class="grid content-start gap-6">
                <template v-if="shop">
                    <ShopKiosks :shop-id="shop.id" :kiosks="shop.kiosks ?? []" />

                    <section class="rounded-3xl border border-line bg-paper p-6">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="grid size-9 place-items-center rounded-xl bg-sky-100 text-sky-800"><Icon name="user-cog" class="size-4" /></span>
                                <h2 class="font-semibold">Gérants</h2>
                            </div>
                            <Link :href="route('managers.create')" class="inline-flex items-center gap-1 text-sm font-medium text-brand-dark">
                                <Icon name="plus" class="size-4" />
                                Ajouter
                            </Link>
                        </div>
                        <ul class="mt-4 grid gap-2">
                            <li v-for="manager in shop.managers" :key="manager.id">
                                <Link
                                    :href="route('managers.edit', manager.id)"
                                    class="flex items-center gap-3 rounded-2xl px-2 py-2 hover:bg-cream/70"
                                    :class="{ 'opacity-60': !manager.isActive }"
                                >
                                    <span class="grid size-9 place-items-center rounded-xl bg-brand/15 text-xs font-semibold text-brand-dark">{{ manager.initials }}</span>
                                    <span class="flex-1 text-sm font-medium">{{ manager.name }}</span>
                                    <span v-if="!manager.isActive" class="text-xs text-rose-700">Désactivé</span>
                                    <Icon name="chevron-right" class="size-4 text-ink/30" />
                                </Link>
                            </li>
                            <li v-if="!shop.managers?.length" class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Aucun gérant : seul le super administrateur gère cette boutique.
                            </li>
                        </ul>
                    </section>

                    <section class="rounded-3xl border border-line bg-paper p-6">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 place-items-center rounded-xl bg-brand/15 text-brand-dark"><Icon name="building" class="size-4" /></span>
                            <div>
                                <h2 class="font-semibold">Départements</h2>
                                <p class="text-xs text-ink/50">Gérés depuis le menu Départements, avec cette boutique sélectionnée.</p>
                            </div>
                        </div>
                        <ul class="mt-4 flex flex-wrap gap-2">
                            <li v-for="department in shop.departments" :key="department.id" class="rounded-full bg-cream px-3 py-1.5 text-sm">
                                {{ department.name }}
                                <span class="ml-1 text-xs text-ink/45 tabular-nums">{{ department.staffCount }}</span>
                            </li>
                            <li v-if="!shop.departments?.length" class="text-sm text-ink/55">Aucun département.</li>
                        </ul>
                    </section>
                </template>

                <section v-else class="rounded-3xl border border-dashed border-brand/40 bg-brand/5 p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-brand text-ink"><Icon name="check-circle" class="size-4" /></span>
                        <h2 class="font-semibold">Préparé automatiquement</h2>
                    </div>
                    <p class="mt-3 text-sm text-ink/65">La boutique sera prête à l’emploi dès sa création :</p>
                    <ul class="mt-3 grid gap-2 text-sm">
                        <li class="flex items-center gap-2">
                            <Icon name="qr" class="size-4 text-brand-dark" />
                            Une borne <strong>Accueil</strong> avec son propre QR code
                        </li>
                        <li class="flex items-start gap-2">
                            <Icon name="building" class="mt-0.5 size-4 text-brand-dark" />
                            <span>Les départements {{ defaultDepartments.join(', ') }}</span>
                        </li>
                    </ul>
                    <p class="mt-4 text-xs text-ink/50">Ensuite : créez son gérant dans « Gérants », puis ajoutez le personnel.</p>
                </section>
            </div>
        </div>

        <div class="fixed inset-x-0 bottom-0 z-10 border-t border-line bg-paper/95 backdrop-blur lg:left-64">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-10">
                <p class="text-sm text-ink/55">
                    <template v-if="form.isDirty">
                        <span class="mr-1.5 inline-block size-2 rounded-full bg-amber-400" />Modifications non enregistrées
                    </template>
                    <template v-else-if="form.recentlySuccessful">Enregistré</template>
                    <template v-else>Aucune modification</template>
                </p>
                <div class="flex items-center gap-2">
                    <Link :href="route('shops.index')" class="inline-flex h-11 items-center rounded-full px-4 text-sm font-medium text-ink/60 hover:text-ink">Annuler</Link>
                    <button
                        type="submit"
                        :disabled="form.processing || (shop !== null && !form.isDirty)"
                        class="inline-flex h-11 items-center gap-2 rounded-full bg-brand px-6 text-sm font-semibold text-ink hover:bg-brand/90 disabled:opacity-60"
                    >
                        <Icon name="check" class="size-4" />
                        {{ shop ? 'Enregistrer' : 'Créer la boutique' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
