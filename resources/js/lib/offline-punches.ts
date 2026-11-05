import { xsrfToken } from '@/lib/http';
import type { PunchTypeName } from '@/types';

export interface PendingPunch {
    uuid: string;
    token: string;
    type: PunchTypeName;
    label: string;
    scannedAt: string;
}

export interface SyncOutcome {
    sent: { punch: PendingPunch; time: string }[];
    rejected: { punch: PendingPunch; message: string }[];
    needsLogin: boolean;
}

const storageKey = 'king-maelick.pending-punches';

export function loadPendingPunches(): PendingPunch[] {
    try {
        return JSON.parse(localStorage.getItem(storageKey) ?? '[]') as PendingPunch[];
    } catch {
        return [];
    }
}

function savePendingPunches(punches: PendingPunch[]): void {
    localStorage.setItem(storageKey, JSON.stringify(punches));
}

export function queuePunch(punch: PendingPunch): PendingPunch[] {
    const punches = [...loadPendingPunches(), punch];
    savePendingPunches(punches);

    return punches;
}

/**
 * crypto.randomUUID is only available over HTTPS, while getRandomValues also works on a local network address.
 */
export function generateUuid(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Sends stored punches in the order they were scanned. Stops at the first network or session problem so nothing is lost.
 */
export async function syncPendingPunches(url: string): Promise<SyncOutcome> {
    const outcome: SyncOutcome = { sent: [], rejected: [], needsLogin: false };
    const remaining = [...loadPendingPunches()].sort((a, b) => a.scannedAt.localeCompare(b.scannedAt));

    while (remaining.length > 0) {
        const punch = remaining[0];
        let response: Response;

        try {
            response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ uuid: punch.uuid, token: punch.token, type: punch.type, scanned_at: punch.scannedAt }),
            });
        } catch {
            break;
        }

        if (response.status === 401 || response.status === 419 || response.redirected) {
            outcome.needsLogin = true;
            break;
        }

        if (response.ok) {
            const data: { time?: string } = await response.json();
            outcome.sent.push({ punch, time: data.time ?? '' });
        } else if (response.status === 422) {
            const data: { message?: string; errors?: Record<string, string[]> } = await response.json();
            const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : undefined;
            outcome.rejected.push({ punch, message: firstError ?? data.message ?? 'Pointage refusé.' });
        } else {
            break;
        }

        remaining.shift();
        savePendingPunches(remaining);
    }

    return outcome;
}
