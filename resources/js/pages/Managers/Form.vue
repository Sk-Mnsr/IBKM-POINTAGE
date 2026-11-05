<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import type { Manager, ShopOption } from '@/types';

const props = defineProps<{
    manager: Manager | null;
    shops: ShopOption[];
    roles: { value: Manager['role']; label: string; description: string }[];
}>();

const form = useForm({
    name: props.manager?.name ?? '',
    phone: props.manager?.phone ?? '',
    email: props.manager?.email ?? '',
    role: props.manager?.role ?? ('admin' as Manager['role']),
    shop_ids: [...(props.manager?.shopIds ?? [])],
    password: '',
    must_change_password: true,
    is_active: props.manager?.isActive ?? true,
});

const showPassword = ref(false);

const toggleShop = (id: number): void => {
    form.shop_ids = form.shop_ids.includes(id) ? form.shop_ids.filter((shopId) => shopId !== id) : [...form.shop_ids, id];
};

const generatePassword = (): void => {
    const alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    const values = crypto.getRandomValues(new Uint32Array(10));
    form.password = [...values].map((value) => alphabet[value % alphabet.length]).join('');
    showPassword.value = true;
};

const submit = (): void => {
    if (props.manager) {
        form.put(route('managers.update', props.manager.id));

        return;
    }

    form.post(route('managers.store'));
};

const inputClasses = 'h-11 w-full rounded-xl border border-line bg-white px-3.5 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';
</script>

<template>
    <Head :title="manager ? manager.name : 'Nouveau gérant'" />

    <Link :href="route('managers.index')" class="inline-flex items-center gap-1 text-sm text-brand-dark">
        <Icon name="chevron-left" class="size-4" />
        Gérants
    </Link>
    <h1 class="mt-4 text-3xl font-semibold tracking-tight">{{ manager ? manager.name : 'Nouveau gérant' }}</h1>

    <form class="mt-6 grid max-w-3xl gap-6" @submit.prevent="submit">
        <section class="rounded-3xl border border-line bg-paper p-6">
            <h2 class="font-semibold">Identité</h2>
            <p class="text-xs text-ink/50">Le téléphone ou l’e-mail sert d’identifiant de connexion.</p>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-medium sm:col-span-2">
                    Nom complet
                    <input v-model="form.name" required autocomplete="off" :class="inputClasses">
                    <span v-if="form.errors.name" class="text-xs font-normal text-rose-700">{{ form.errors.name }}</span>
                </label>
                <label class="grid gap-1.5 text-sm font-medium">
                    Téléphone
                    <input v-model="form.phone" inputmode="numeric" required placeholder="77 000 00 00" :class="inputClasses">
                    <span v-if="form.errors.phone" class="text-xs font-normal text-rose-700">{{ form.errors.phone }}</span>
                </label>
                <label class="grid gap-1.5 text-sm font-medium">
                    E-mail <span class="-mt-1.5 text-xs font-normal text-ink/45">facultatif</span>
                    <input v-model="form.email" type="email" placeholder="nom@exemple.sn" :class="inputClasses">
                    <span v-if="form.errors.email" class="text-xs font-normal text-rose-700">{{ form.errors.email }}</span>
                </label>
            </div>
        </section>

        <section class="rounded-3xl border border-line bg-paper p-6">
            <h2 class="font-semibold">Profil et boutiques</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button
                    v-for="role in roles"
                    :key="role.value"
                    type="button"
                    class="rounded-2xl border p-4 text-left transition"
                    :class="form.role === role.value ? 'border-brand bg-brand/10 ring-4 ring-brand/15' : 'border-line bg-white hover:border-ink/30'"
                    @click="form.role = role.value"
                >
                    <span class="flex items-center gap-2 font-medium">
                        <Icon :name="role.value === 'super_admin' ? 'shield' : 'store'" class="size-4" />
                        {{ role.label }}
                    </span>
                    <span class="mt-1 block text-xs text-ink/55">{{ role.description }}</span>
                </button>
            </div>
            <span v-if="form.errors.role" class="mt-2 block text-xs text-rose-700">{{ form.errors.role }}</span>

            <fieldset v-if="form.role === 'admin'" class="mt-5">
                <legend class="text-sm font-medium">Boutiques confiées</legend>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        v-for="shop in shops"
                        :key="shop.id"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition"
                        :class="form.shop_ids.includes(shop.id) ? 'border-ink bg-ink text-cream' : 'border-line bg-white text-ink/70 hover:border-ink/30'"
                        @click="toggleShop(shop.id)"
                    >
                        <Icon :name="form.shop_ids.includes(shop.id) ? 'check' : 'store'" class="size-4" />
                        {{ shop.name }}
                        <span v-if="shop.isActive === false" class="text-xs opacity-60">(fermée)</span>
                    </button>
                </div>
                <span v-if="form.errors.shop_ids" class="mt-1 block text-xs text-rose-700">{{ form.errors.shop_ids }}</span>
            </fieldset>

            <div class="mt-5 flex items-center justify-between gap-4 rounded-2xl bg-cream/70 px-4 py-3">
                <div>
                    <p class="text-sm font-medium">Compte actif</p>
                    <p class="text-xs text-ink/50">Un compte désactivé ne peut plus se connecter.</p>
                </div>
                <ToggleSwitch v-model="form.is_active" label="Compte actif" />
            </div>
            <span v-if="form.errors.is_active" class="mt-1 block text-xs text-rose-700">{{ form.errors.is_active }}</span>
        </section>

        <section class="rounded-3xl border border-line bg-paper p-6">
            <h2 class="font-semibold">Mot de passe</h2>
            <p class="text-xs text-ink/50">{{ manager ? 'Laissez vide pour garder le mot de passe actuel.' : 'Au moins 8 caractères, à transmettre au gérant.' }}</p>
            <div class="mt-4 flex gap-2">
                <div class="relative flex-1">
                    <input
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="new-password"
                        :required="!manager"
                        :class="[inputClasses, 'pr-20 font-mono']"
                    >
                    <button type="button" class="absolute inset-y-0 right-3 text-xs font-medium text-ink/50 hover:text-ink" @click="showPassword = !showPassword">
                        {{ showPassword ? 'Masquer' : 'Afficher' }}
                    </button>
                </div>
                <button type="button" class="h-11 rounded-xl border border-line bg-white px-4 text-sm font-medium hover:border-ink/30" @click="generatePassword">
                    Générer
                </button>
            </div>
            <span v-if="form.errors.password" class="mt-1 block text-xs text-rose-700">{{ form.errors.password }}</span>

            <div v-if="!manager || form.password !== ''" class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-cream/70 px-4 py-3">
                <div>
                    <p class="text-sm font-medium">Changement obligatoire à la prochaine connexion</p>
                    <p class="text-xs text-ink/50">Le gérant choisira son propre mot de passe en se connectant.</p>
                </div>
                <ToggleSwitch v-model="form.must_change_password" label="Changement obligatoire à la prochaine connexion" />
            </div>
        </section>

        <div class="flex items-center justify-end gap-2">
            <Link :href="route('managers.index')" class="inline-flex h-11 items-center rounded-full px-4 text-sm font-medium text-ink/60 hover:text-ink">Annuler</Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex h-11 items-center gap-2 rounded-full bg-brand px-6 text-sm font-semibold text-ink hover:bg-brand/90 disabled:opacity-60"
            >
                <Icon name="check" class="size-4" />
                {{ manager ? 'Enregistrer' : 'Créer le gérant' }}
            </button>
        </div>
    </form>
</template>
