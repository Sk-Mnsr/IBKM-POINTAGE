<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';
import Icon from '@/components/Icon.vue';

const props = defineProps<{
    forced: boolean;
    homeUrl: string;
    changedAt: string | null;
}>();

const page = usePage();
const showPasswords = ref(false);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const checks = computed(() => [
    { label: 'Au moins 8 caractères', ok: form.password.length >= 8 },
    { label: 'Contient un chiffre', ok: /\d/.test(form.password) },
    { label: 'Les deux saisies correspondent', ok: form.password !== '' && form.password === form.password_confirmation },
]);

const submit = (): void => {
    form.put(route('password.update'), {
        onFinish: () => form.reset('current_password', 'password', 'password_confirmation'),
    });
};

const inputClasses = 'h-12 w-full rounded-2xl border border-line bg-white px-4 outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';
</script>

<template>
    <Head title="Mot de passe" />

    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col justify-center px-4 py-10">
        <div class="flex items-center justify-between">
            <AppLogo class="w-32" />
            <Link v-if="!props.forced" :href="homeUrl" class="text-sm font-medium text-brand-dark">Retour</Link>
            <Link v-else :href="route('logout')" method="post" as="button" class="text-sm text-ink/50">Se déconnecter</Link>
        </div>

        <div class="mt-10">
            <span class="grid size-12 place-items-center rounded-2xl bg-brand/15 text-brand-dark">
                <Icon name="shield" class="size-6" />
            </span>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight">
                {{ forced ? 'Choisissez votre mot de passe' : 'Changer mon mot de passe' }}
            </h1>
            <p class="mt-2 text-ink/65">
                <template v-if="forced">
                    Bienvenue {{ page.props.auth.user?.name.split(' ')[0] }} ! Le mot de passe actuel vous a été donné par le gérant :
                    remplacez-le par un mot de passe personnel que vous seul connaissez.
                </template>
                <template v-else>
                    {{ changedAt ? `Dernier changement le ${changedAt}.` : 'Utilisez un mot de passe que vous seul connaissez.' }}
                </template>
            </p>
        </div>

        <form class="mt-8 grid gap-4" @submit.prevent="submit">
            <label v-if="!forced" class="grid gap-1.5 text-sm font-medium">
                Mot de passe actuel
                <input v-model="form.current_password" :type="showPasswords ? 'text' : 'password'" autocomplete="current-password" required :class="inputClasses">
                <span v-if="form.errors.current_password" class="font-normal text-rose-700">{{ form.errors.current_password }}</span>
            </label>

            <label class="grid gap-1.5 text-sm font-medium">
                Nouveau mot de passe
                <input v-model="form.password" :type="showPasswords ? 'text' : 'password'" autocomplete="new-password" required :class="inputClasses">
                <span v-if="form.errors.password" class="font-normal text-rose-700">{{ form.errors.password }}</span>
            </label>

            <label class="grid gap-1.5 text-sm font-medium">
                Confirmer le nouveau mot de passe
                <input v-model="form.password_confirmation" :type="showPasswords ? 'text' : 'password'" autocomplete="new-password" required :class="inputClasses">
            </label>

            <ul class="grid gap-1.5 rounded-2xl bg-cream px-4 py-3 text-sm">
                <li v-for="check in checks" :key="check.label" class="flex items-center gap-2" :class="check.ok ? 'text-brand-dark' : 'text-ink/45'">
                    <Icon :name="check.ok ? 'check-circle' : 'clock'" class="size-4" />
                    {{ check.label }}
                </li>
            </ul>

            <label class="flex items-center gap-2 text-sm text-ink/60">
                <input v-model="showPasswords" type="checkbox" class="size-4 accent-ink">
                Afficher les mots de passe
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="mt-2 inline-flex h-12 items-center justify-center gap-2 rounded-full bg-ink font-semibold text-cream hover:bg-ink/90 disabled:opacity-60"
            >
                <Icon name="check" class="size-4" />
                Enregistrer mon mot de passe
            </button>
        </form>
    </main>
</template>
