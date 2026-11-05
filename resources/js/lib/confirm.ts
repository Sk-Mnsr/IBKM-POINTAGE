import { ref } from 'vue';

export interface ConfirmOptions {
    title: string;
    message?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    danger?: boolean;
}

interface PendingConfirmation extends ConfirmOptions {
    resolve: (confirmed: boolean) => void;
}

export const pendingConfirmation = ref<PendingConfirmation | null>(null);

/**
 * In-app replacement for window.confirm(), rendered by ConfirmDialog.
 */
export function confirmAction(options: ConfirmOptions): Promise<boolean> {
    pendingConfirmation.value?.resolve(false);

    return new Promise((resolve) => {
        pendingConfirmation.value = { ...options, resolve };
    });
}

export function settleConfirmation(confirmed: boolean): void {
    pendingConfirmation.value?.resolve(confirmed);
    pendingConfirmation.value = null;
}
