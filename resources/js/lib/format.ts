import type { PunchTypeName } from '@/types';

export function formatDuration(minutes: number, empty = '—'): string {
    if (minutes <= 0) {
        return empty;
    }

    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours === 0) {
        return `${rest} min`;
    }

    return `${hours} h ${String(rest).padStart(2, '0')}`;
}

export function isCurrentPath(pageUrl: string, href: string): boolean {
    const current = pageUrl.split('?')[0];
    const target = new URL(href, window.location.origin).pathname;

    return current === target || current.startsWith(`${target}/`);
}

export function formatRate(rate: number | null): string {
    return rate === null ? '—' : `${rate} %`;
}

export function punchIcon(type: PunchTypeName): string {
    if (type === 'arrival') {
        return 'login';
    }

    if (type === 'departure') {
        return 'logout';
    }

    return 'coffee';
}

export function punchCircleClasses(type: PunchTypeName): string {
    if (type === 'arrival') {
        return 'bg-brand/15 text-brand-dark';
    }

    if (type === 'departure') {
        return 'bg-stone-200 text-stone-700';
    }

    return 'bg-amber-100 text-amber-800';
}

export function punchTone(type: PunchTypeName): 'emerald' | 'stone' | 'amber' {
    if (type === 'arrival') {
        return 'emerald';
    }

    if (type === 'departure') {
        return 'stone';
    }

    return 'amber';
}
