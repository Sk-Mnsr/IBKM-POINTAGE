import type { WorkScheduleInput } from '@/types';

const shortDays = ['', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

export function minutesBetween(start: string, end: string): number {
    if (!start || !end) {
        return 0;
    }

    const [startHours, startMinutes] = start.split(':').map(Number);
    const [endHours, endMinutes] = end.split(':').map(Number);

    return Math.max(0, endHours * 60 + endMinutes - (startHours * 60 + startMinutes));
}

export function weeklyMinutes(schedules: (WorkScheduleInput & { weekday?: number })[]): number {
    return schedules.reduce((total, schedule) => total + minutesBetween(schedule.starts_at, schedule.ends_at), 0);
}

/**
 * Compact label such as "Lun–Sam · 09:00–18:00" when every worked day shares the same hours.
 */
export function scheduleSummary(schedules: (WorkScheduleInput & { weekday: number })[]): string {
    if (schedules.length === 0) {
        return 'Aucun horaire';
    }

    const sorted = [...schedules].sort((a, b) => a.weekday - b.weekday);
    const sameHours = sorted.every((day) => day.starts_at === sorted[0].starts_at && day.ends_at === sorted[0].ends_at);
    const consecutive = sorted.every((day, index) => index === 0 || day.weekday === sorted[index - 1].weekday + 1);

    const days = consecutive && sorted.length > 2
        ? `${shortDays[sorted[0].weekday]}–${shortDays[sorted[sorted.length - 1].weekday]}`
        : sorted.map((day) => shortDays[day.weekday]).join(', ');

    return sameHours ? `${days} · ${sorted[0].starts_at}–${sorted[0].ends_at}` : `${days} · horaires variables`;
}
