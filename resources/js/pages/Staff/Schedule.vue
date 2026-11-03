<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import StatusBadge from '@/components/StatusBadge.vue';
import type { DailyAttendance, LeaveRequest } from '@/types';

const props = defineProps<{
    weeks: DailyAttendance[][];
    upcomingLeaves: LeaveRequest[];
}>();

const sections = computed(() =>
    props.weeks
        .map((week, index) => ({
            title: index === 0 ? 'Cette semaine' : 'Semaine prochaine',
            days: [...week].reverse(),
        }))
        .reverse(),
);
</script>

<template>
    <Head title="Mon planning" />

    <div class="grid gap-5">
        <h1 class="text-2xl font-semibold">Mon planning</h1>

        <section v-for="section in sections" :key="section.title" class="rounded-3xl border border-line bg-paper p-5">
            <h2 class="font-semibold">{{ section.title }}</h2>
            <ul class="mt-4 grid gap-2">
                <li
                    v-for="day in section.days"
                    :key="day.date"
                    class="flex items-center gap-3 rounded-2xl px-4 py-3"
                    :class="day.isToday ? 'bg-ink text-cream' : 'bg-cream/60'"
                >
                    <div class="w-24 shrink-0">
                        <p class="text-sm font-semibold">{{ day.weekdayLong }}</p>
                        <p class="text-xs" :class="day.isToday ? 'text-cream/60' : 'text-ink/55'">{{ day.monthDay }}</p>
                    </div>
                    <p class="min-w-0 flex-1 text-sm font-medium whitespace-nowrap tabular-nums">{{ day.leave ? day.leave.type : day.scheduleLabel }}</p>
                    <StatusBadge v-if="day.leave" tone="sky" label="Congé" />
                    <StatusBadge v-else-if="!day.isScheduled && day.punches.length === 0" tone="neutral" label="Repos" />
                    <StatusBadge v-else-if="day.status.value !== 'upcoming'" :tone="day.status.tone" :label="day.status.label" />
                </li>
            </ul>
        </section>

        <section class="rounded-3xl border border-line bg-paper p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Mes congés à venir</h2>
                <Link :href="route('staff.leaves.index')" class="text-sm text-brand-dark">Demander</Link>
            </div>
            <ul class="mt-4 grid gap-2 text-sm">
                <li v-for="leave in upcomingLeaves" :key="leave.id" class="flex items-center justify-between rounded-2xl bg-sky-50 px-4 py-3 text-sky-900">
                    <span class="font-medium">{{ leave.type.label }}</span>
                    <span>{{ leave.startsLabel }} → {{ leave.endsLabel }}</span>
                </li>
                <li v-if="upcomingLeaves.length === 0" class="text-ink/55">Aucun congé accepté à venir.</li>
            </ul>
        </section>
    </div>
</template>
