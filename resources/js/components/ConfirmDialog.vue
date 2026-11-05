<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from '@/components/Icon.vue';
import { pendingConfirmation, settleConfirmation } from '@/lib/confirm';

const confirmButton = ref<HTMLButtonElement | null>(null);

watch(pendingConfirmation, async (confirmation) => {
    if (confirmation) {
        await nextTick();
        confirmButton.value?.focus();
    }
});

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && pendingConfirmation.value) {
        settleConfirmation(false);
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="pendingConfirmation"
                class="fixed inset-0 z-50 grid place-items-center bg-ink/50 p-4 backdrop-blur-sm"
                @click.self="settleConfirmation(false)"
            >
                <div
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="confirm-dialog-title"
                    class="w-full max-w-sm rounded-3xl border border-line bg-paper p-6 text-ink shadow-2xl"
                >
                    <span
                        class="grid size-11 place-items-center rounded-2xl"
                        :class="pendingConfirmation.danger ? 'bg-rose-50 text-rose-700' : 'bg-brand/15 text-brand-dark'"
                    >
                        <Icon :name="pendingConfirmation.danger ? 'alert' : 'check-circle'" />
                    </span>
                    <h2 id="confirm-dialog-title" class="mt-4 text-lg font-semibold tracking-tight">{{ pendingConfirmation.title }}</h2>
                    <p v-if="pendingConfirmation.message" class="mt-1.5 text-sm text-ink/60">{{ pendingConfirmation.message }}</p>

                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center rounded-full px-5 text-sm font-medium text-ink/60 hover:bg-cream hover:text-ink"
                            @click="settleConfirmation(false)"
                        >
                            {{ pendingConfirmation.cancelLabel ?? 'Annuler' }}
                        </button>
                        <button
                            ref="confirmButton"
                            type="button"
                            class="inline-flex h-11 items-center rounded-full px-5 text-sm font-semibold outline-none focus-visible:ring-4"
                            :class="pendingConfirmation.danger
                                ? 'bg-rose-600 text-white hover:bg-rose-700 focus-visible:ring-rose-200'
                                : 'bg-brand text-ink hover:bg-brand/90 focus-visible:ring-brand/30'"
                            @click="settleConfirmation(true)"
                        >
                            {{ pendingConfirmation.confirmLabel ?? 'Confirmer' }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
