<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import ToggleSwitch from '@/components/ToggleSwitch.vue';
import { confirmAction } from '@/lib/confirm';
import { formatDuration } from '@/lib/format';
import { minutesBetween } from '@/lib/schedule';
import type { Department, Employee, ShopOption, WorkScheduleInput } from '@/types';

const props = defineProps<{
    employee: Employee | null;
    shops: ShopOption[];
    defaultShopId: number | null;
    departments: Department[];
    weekdays: Record<string, string>;
}>();

const page = usePage();
const canDeleteEmployee = computed(() => page.props.auth.user?.role === 'super_admin');

const schedules: Record<string, WorkScheduleInput> = Object.fromEntries(
    Object.keys(props.weekdays).map((weekday) => {
        const existing = props.employee?.schedules?.find((schedule) => schedule.weekday === Number(weekday));

        return [weekday, { starts_at: existing?.starts_at ?? '', ends_at: existing?.ends_at ?? '' }];
    }),
);

const form = useForm({
    name: props.employee?.name ?? '',
    phone: props.employee?.phone ?? '',
    email: props.employee?.email ?? '',
    shop_id: props.employee?.shopId ?? props.defaultShopId ?? ('' as number | ''),
    department_id: props.employee?.departmentId ?? ('' as number | ''),
    password: '',
    must_change_password: true,
    is_active: props.employee?.isActive ?? true,
    schedules,
});

const showPassword = ref(false);

const initials = computed(() =>
    form.name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('') || '?',
);

const shopDepartments = computed(() => props.departments.filter((department) => department.shopId === form.shop_id));
const shopName = computed(() => props.shops.find((shop) => shop.id === form.shop_id)?.name ?? null);

watch(
    () => form.shop_id,
    () => {
        if (!shopDepartments.value.some((department) => department.id === form.department_id)) {
            form.department_id = '';
        }
    },
);

const departmentName = computed(() => props.departments.find((department) => department.id === form.department_id)?.name ?? 'Sans département');

const isWorking = (weekday: string): boolean => form.schedules[weekday].starts_at !== '' || form.schedules[weekday].ends_at !== '';

const lastWorkedHours = (): WorkScheduleInput => {
    const worked = Object.values(form.schedules).filter((schedule) => schedule.starts_at && schedule.ends_at);

    return worked[worked.length - 1] ?? { starts_at: '09:00', ends_at: '18:00' };
};

const setWorking = (weekday: string, working: boolean): void => {
    if (!working) {
        form.schedules[weekday] = { starts_at: '', ends_at: '' };

        return;
    }

    form.schedules[weekday] = { ...lastWorkedHours() };
};

const applyPreset = (days: string[], hours: WorkScheduleInput): void => {
    for (const weekday of Object.keys(props.weekdays)) {
        form.schedules[weekday] = days.includes(weekday) ? { ...hours } : { starts_at: '', ends_at: '' };
    }
};

const copyFirstDayToAll = (): void => {
    const first = Object.values(form.schedules).find((schedule) => schedule.starts_at && schedule.ends_at);

    if (!first) {
        return;
    }

    for (const weekday of Object.keys(props.weekdays)) {
        if (isWorking(weekday)) {
            form.schedules[weekday] = { ...first };
        }
    }
};

const workedDays = computed(() => Object.keys(props.weekdays).filter(isWorking).length);
const weeklyTotal = computed(() =>
    Object.values(form.schedules).reduce((total, schedule) => total + minutesBetween(schedule.starts_at, schedule.ends_at), 0),
);

const generatePassword = (): void => {
    const alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
    const values = crypto.getRandomValues(new Uint32Array(10));
    form.password = [...values].map((value) => alphabet[value % alphabet.length]).join('');
    showPassword.value = true;
};

const errorFor = (key: string): string | undefined => (form.errors as Record<string, string | undefined>)[key];

const submit = (): void => {
    if (props.employee) {
        form.put(route('employees.update', props.employee.id), { preserveScroll: true });

        return;
    }

    form.post(route('employees.store'));
};

const removeEmployee = async (): Promise<void> => {
    if (!props.employee || !canDeleteEmployee.value) {
        return;
    }

    const confirmed = await confirmAction({
        title: `Supprimer ${props.employee.name} ?`,
        message: 'Ses pointages, congés et horaires seront aussi effacés. Cette action est définitive.',
        confirmLabel: 'Supprimer',
        danger: true,
    });

    if (confirmed) {
        router.delete(route('employees.destroy', props.employee.id));
    }
};

const inputClasses = 'h-11 w-full rounded-xl border border-line bg-white px-3.5 text-sm outline-none transition focus:border-brand focus:ring-4 focus:ring-brand/15';
</script>

<template>
    <Head :title="employee ? employee.name : 'Nouvel employé'" />

    <form class="grid gap-6 pb-24" @submit.prevent="submit">
        <Link :href="route('employees.index')" class="inline-flex w-fit items-center gap-1 text-sm font-medium text-brand-dark">
            <Icon name="chevron-left" class="size-4" />
            Employés
        </Link>

        <header class="relative overflow-hidden rounded-3xl bg-ink p-6 text-cream">
            <div class="pointer-events-none absolute -top-20 -right-16 size-56 rounded-full bg-brand/20 blur-3xl" />
            <div class="relative flex flex-wrap items-center justify-between gap-5">
                <div class="flex items-center gap-4">
                    <span class="grid size-16 place-items-center rounded-2xl bg-brand text-xl font-semibold text-ink">{{ initials }}</span>
                    <div>
                        <p class="text-sm text-cream/55">{{ employee ? 'Modifier la fiche' : 'Nouvel employé' }}</p>
                        <h1 class="text-2xl font-semibold tracking-tight">{{ form.name || 'Nom de l’employé' }}</h1>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
                            <span v-if="shops.length > 1 && shopName" class="rounded-full bg-brand/20 px-2.5 py-0.5 text-brand">{{ shopName }}</span>
                            <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-cream/80">{{ departmentName }}</span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5"
                                :class="form.is_active ? 'bg-brand/20 text-brand' : 'bg-rose-500/20 text-rose-200'"
                            >
                                <span class="size-1.5 rounded-full" :class="form.is_active ? 'bg-brand' : 'bg-rose-300'" />
                                {{ form.is_active ? 'Compte actif' : 'Compte désactivé' }}
                            </span>
                            <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-cream/80">{{ workedDays }} j · {{ formatDuration(weeklyTotal, '0 h') }} / semaine</span>
                        </div>
                    </div>
                </div>
                <Link
                    v-if="employee"
                    :href="route('reports.employee', employee.id)"
                    class="inline-flex h-10 items-center gap-2 rounded-full border border-white/15 px-4 text-sm font-medium text-cream/80 hover:bg-white/5 hover:text-cream"
                >
                    <Icon name="chart" class="size-4" />
                    Fiche de présence
                </Link>
            </div>
        </header>

        <div class="grid gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div class="grid content-start gap-6">
                <section class="rounded-3xl border border-line bg-paper p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-brand/15 text-brand-dark"><Icon name="user" class="size-4" /></span>
                        <div>
                            <h2 class="font-semibold">Identité</h2>
                            <p class="text-xs text-ink/50">Le téléphone sert d’identifiant de connexion.</p>
                        </div>
                    </div>
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
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-sky-100 text-sky-800"><Icon name="building" class="size-4" /></span>
                        <h2 class="font-semibold">Poste</h2>
                    </div>
                    <fieldset v-if="shops.length > 1" class="mt-5">
                        <legend class="text-sm font-medium">Boutique</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="shop in shops"
                                :key="shop.id"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm transition"
                                :class="form.shop_id === shop.id ? 'border-brand bg-brand/15 font-medium text-ink' : 'border-line bg-white text-ink/70 hover:border-ink/30'"
                                @click="form.shop_id = shop.id"
                            >
                                <Icon name="store" class="size-4" />
                                {{ shop.name }}
                            </button>
                        </div>
                        <span v-if="form.errors.shop_id" class="mt-1 block text-xs text-rose-700">{{ form.errors.shop_id }}</span>
                    </fieldset>

                    <fieldset class="mt-5">
                        <legend class="text-sm font-medium">Département</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-full border px-4 py-2 text-sm transition"
                                :class="form.department_id === '' ? 'border-ink bg-ink text-cream' : 'border-line bg-white text-ink/70 hover:border-ink/30'"
                                @click="form.department_id = ''"
                            >
                                Aucun
                            </button>
                            <button
                                v-for="department in shopDepartments"
                                :key="department.id"
                                type="button"
                                class="rounded-full border px-4 py-2 text-sm transition"
                                :class="form.department_id === department.id ? 'border-ink bg-ink text-cream' : 'border-line bg-white text-ink/70 hover:border-ink/30'"
                                @click="form.department_id = department.id"
                            >
                                {{ department.name }}
                            </button>
                        </div>
                        <span v-if="form.errors.department_id" class="mt-1 block text-xs text-rose-700">{{ form.errors.department_id }}</span>
                    </fieldset>

                    <div class="mt-5 flex items-center justify-between gap-4 rounded-2xl bg-cream/70 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium">Compte actif</p>
                            <p class="text-xs text-ink/50">Un compte désactivé ne peut plus se connecter ni pointer.</p>
                        </div>
                        <ToggleSwitch v-model="form.is_active" label="Compte actif" />
                    </div>
                </section>

                <section class="rounded-3xl border border-line bg-paper p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-amber-100 text-amber-800"><Icon name="shield" class="size-4" /></span>
                        <div>
                            <h2 class="font-semibold">Mot de passe</h2>
                            <p class="text-xs text-ink/50">{{ employee ? 'Laissez vide pour garder le mot de passe actuel.' : 'Au moins 8 caractères, à transmettre à l’employé.' }}</p>
                        </div>
                    </div>
                    <div class="mt-5 flex gap-2">
                        <div class="relative flex-1">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="new-password"
                                :required="!employee"
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

                    <div v-if="!employee || form.password !== ''" class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-cream/70 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium">Changement obligatoire à la prochaine connexion</p>
                            <p class="text-xs text-ink/50">L’employé choisira son propre mot de passe en se connectant. Recommandé.</p>
                        </div>
                        <ToggleSwitch v-model="form.must_change_password" label="Changement obligatoire à la prochaine connexion" />
                    </div>
                    <p v-else-if="employee?.mustChangePassword" class="mt-4 flex items-center gap-2 rounded-2xl bg-amber-50 px-4 py-3 text-xs text-amber-900">
                        <Icon name="alert" class="size-4" />
                        Mot de passe provisoire : l’employé devra le changer à sa prochaine connexion.
                    </p>
                </section>
            </div>

            <section class="rounded-3xl border border-line bg-paper p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 place-items-center rounded-xl bg-ink text-cream"><Icon name="calendar" class="size-4" /></span>
                        <div>
                            <h2 class="font-semibold">Horaires de la semaine</h2>
                            <p class="text-xs text-ink/50">Base du calcul des retards et des heures supplémentaires.</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-semibold tracking-tight tabular-nums">{{ formatDuration(weeklyTotal, '0 h') }}</p>
                        <p class="text-xs text-ink/50">{{ workedDays }} jour(s) travaillé(s)</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <button
                        type="button"
                        class="rounded-full bg-cream px-3 py-1.5 font-medium text-ink/70 hover:text-ink"
                        @click="applyPreset(['1', '2', '3', '4', '5', '6'], { starts_at: '09:00', ends_at: '18:00' })"
                    >
                        Lun–Sam · 9h–18h
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-cream px-3 py-1.5 font-medium text-ink/70 hover:text-ink"
                        @click="applyPreset(['2', '3', '4', '5', '6', '7'], { starts_at: '10:00', ends_at: '19:00' })"
                    >
                        Mar–Dim · 10h–19h
                    </button>
                    <button type="button" class="rounded-full bg-cream px-3 py-1.5 font-medium text-ink/70 hover:text-ink" @click="copyFirstDayToAll">
                        Mêmes horaires pour tous les jours travaillés
                    </button>
                </div>

                <ul class="mt-5 grid gap-2">
                    <li
                        v-for="(label, weekday) in weekdays"
                        :key="weekday"
                        class="rounded-2xl border px-4 py-3 transition"
                        :class="isWorking(String(weekday)) ? 'border-line bg-white' : 'border-transparent bg-cream/60'"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <ToggleSwitch
                                :model-value="isWorking(String(weekday))"
                                :label="`${label} travaillé`"
                                @update:model-value="setWorking(String(weekday), $event)"
                            />
                            <span class="w-24 text-sm font-medium">{{ label }}</span>
                            <template v-if="isWorking(String(weekday))">
                                <input
                                    v-model="form.schedules[weekday].starts_at"
                                    type="time"
                                    :aria-label="`${label} début`"
                                    class="h-10 w-28 rounded-xl border border-line bg-white px-3 text-sm tabular-nums outline-none focus:border-brand"
                                >
                                <span class="text-ink/30">→</span>
                                <input
                                    v-model="form.schedules[weekday].ends_at"
                                    type="time"
                                    :aria-label="`${label} fin`"
                                    class="h-10 w-28 rounded-xl border border-line bg-white px-3 text-sm tabular-nums outline-none focus:border-brand"
                                >
                                <span class="ml-auto text-xs font-medium tabular-nums text-ink/50">
                                    {{ formatDuration(minutesBetween(form.schedules[weekday].starts_at, form.schedules[weekday].ends_at)) }}
                                </span>
                            </template>
                            <span v-else class="text-sm text-ink/40">Repos</span>
                        </div>
                        <p v-if="errorFor(`schedules.${weekday}.starts_at`) || errorFor(`schedules.${weekday}.ends_at`)" class="mt-2 text-xs text-rose-700">
                            {{ errorFor(`schedules.${weekday}.starts_at`) ?? errorFor(`schedules.${weekday}.ends_at`) }}
                        </p>
                    </li>
                </ul>
            </section>
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
                    <button
                        v-if="employee && canDeleteEmployee"
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-full px-4 text-sm font-medium text-rose-700 hover:bg-rose-50"
                        @click="removeEmployee"
                    >
                        Supprimer
                    </button>
                    <Link :href="route('employees.index')" class="inline-flex h-11 items-center rounded-full px-4 text-sm font-medium text-ink/60 hover:text-ink">Annuler</Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-11 items-center gap-2 rounded-full bg-brand px-6 text-sm font-semibold text-ink hover:bg-brand/90 disabled:opacity-60"
                    >
                        <Icon name="check" class="size-4" />
                        {{ employee ? 'Enregistrer' : 'Créer l’employé' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</template>
