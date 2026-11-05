<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { route } from 'ziggy-js';
import Icon from '@/components/Icon.vue';
import type { ShopOption } from '@/types';

const props = defineProps<{ shops: ShopOption[]; currentId: number | null }>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const optionElements = ref<HTMLButtonElement[]>([]);

const options = computed(() => [
    { id: null as number | null, name: 'Toutes les boutiques' },
    ...props.shops.map((shop) => ({ id: shop.id as number | null, name: shop.name })),
]);

const currentLabel = computed(() => options.value.find((option) => option.id === props.currentId)?.name ?? 'Toutes les boutiques');

const toggle = async (): Promise<void> => {
    open.value = !open.value;

    if (open.value) {
        await nextTick();
        const selectedIndex = options.value.findIndex((option) => option.id === props.currentId);
        optionElements.value[Math.max(selectedIndex, 0)]?.focus();
    }
};

const select = (id: number | null): void => {
    open.value = false;

    if (id === props.currentId) {
        return;
    }

    router.put(route('current-shop.update'), { shop_id: id }, { preserveScroll: true });
};

const moveFocus = (step: number): void => {
    const elements = optionElements.value;
    const index = elements.findIndex((element) => element === document.activeElement);
    elements[(index + step + elements.length) % elements.length]?.focus();
};

const onDocumentClick = (event: MouseEvent): void => {
    if (open.value && root.value && !root.value.contains(event.target as Node)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative" @keydown.esc="open = false">
        <button
            type="button"
            aria-haspopup="listbox"
            :aria-expanded="open"
            class="flex w-full items-center gap-2 rounded-xl border bg-white/5 px-3 py-2.5 text-left text-sm text-cream transition hover:bg-white/10"
            :class="open ? 'border-brand' : 'border-white/10'"
            @click="toggle"
        >
            <span class="flex-1 truncate">{{ currentLabel }}</span>
            <Icon name="chevron-right" class="size-4 shrink-0 text-cream/50 transition" :class="open ? '-rotate-90' : 'rotate-90'" />
        </button>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-75 ease-in"
            leave-to-class="opacity-0"
        >
            <ul
                v-if="open"
                role="listbox"
                class="absolute inset-x-0 top-full z-30 mt-1.5 grid max-h-72 gap-0.5 overflow-y-auto rounded-xl border border-white/10 bg-[#1c1f1c] p-1.5 shadow-2xl shadow-black/50"
                @keydown.down.prevent="moveFocus(1)"
                @keydown.up.prevent="moveFocus(-1)"
            >
                <li v-for="option in options" :key="option.id ?? 'all'">
                    <button
                        ref="optionElements"
                        type="button"
                        role="option"
                        :aria-selected="option.id === currentId"
                        class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm outline-none transition focus-visible:bg-white/10"
                        :class="option.id === currentId ? 'bg-brand/15 font-medium text-brand' : 'text-cream/75 hover:bg-white/5 hover:text-cream'"
                        @click="select(option.id)"
                    >
                        <Icon :name="option.id === null ? 'dashboard' : 'store'" class="size-4 shrink-0 opacity-70" />
                        <span class="flex-1 truncate">{{ option.name }}</span>
                        <Icon v-if="option.id === currentId" name="check" class="size-4 shrink-0" />
                    </button>
                </li>
            </ul>
        </Transition>
    </div>
</template>
