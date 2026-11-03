<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MonthPicker from '@/components/MonthPicker.vue';
import StatCard from '@/components/StatCard.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { formatDuration } from '@/lib/format';
import type { AttendanceSummary, DailyAttendance, ReportMonth } from '@/types';

defineProps<{
    month: ReportMonth;
    days: DailyAttendance[];
    summary: AttendanceSummary;
}>();
</script>

<template>
    <Head title="Mon historique" />

    <div class="grid gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Mon historique</h1>
            <MonthPicker :month="month" route-name="staff.history" />
        </div>

        <section class="grid grid-cols-2 gap-3">
            <StatCard label="Jours travaillés" :value="summary.workedDays" icon="calendar" :hint="`${summary.absentDays} absence(s)`" />
            <StatCard label="Heures" :value="formatDuration(summary.workedMinutes, '0 h')" icon="clock" tone="emerald" />
            <StatCard label="Retards" :value="summary.lateCount" icon="timer" tone="amber" :hint="formatDuration(summary.lateMinutes, '0 min')" />
            <StatCard label="Heures sup" :value="formatDuration(summary.overtimeMinutes, '0 h')" icon="trending" tone="brand" />
        </section>

        <section class="overflow-hidden rounded-3xl border border-line bg-paper">
            <ul class="divide-y divide-line">
                <li v-for="day in days" :key="day.date" class="flex items-center gap-4 px-5 py-3.5" :class="{ 'opacity-55': day.status.value === 'day_off' }">
                    <div class="w-12 text-center">
                        <p class="text-xs text-ink/55 uppercase">{{ day.weekdayShort }}</p>
                        <p class="text-lg font-semibold tabular-nums">{{ day.dayNumber }}</p>
                    </div>
                    <div class="min-w-0 flex-1">
                        <StatusBadge :tone="day.status.tone" :label="day.status.label" />
                        <p class="mt-1 text-xs text-ink/55 tabular-nums">
                            <template v-if="day.firstArrival">
                                {{ day.firstArrival }} → {{ day.lastDeparture ?? '…' }}
                                <span v-if="day.lateMinutes > 0" class="font-medium text-amber-700">· retard {{ day.lateMinutes }} min</span>
                            </template>
                            <template v-else>Prévu : {{ day.scheduleLabel }}</template>
                        </p>
                    </div>
                    <p class="text-sm font-semibold tabular-nums">{{ formatDuration(day.workedMinutes) }}</p>
                </li>
            </ul>
        </section>
    </div>
</template>
