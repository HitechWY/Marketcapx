<script setup lang="ts">
import { useFlash } from '@/composables/useFlash';

const { toasts, dismiss } = useFlash();

function toneClass(type: string): string {
    switch (type) {
        case 'success':
            return 'bg-emerald-600 text-white';
        case 'error':
            return 'bg-md-error text-white';
        case 'warning':
            return 'bg-amber-500 text-white';
        default:
            return 'bg-md-primary text-md-on-primary';
    }
}
</script>

<template>
    <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <transition-group name="toast">
            <div
                v-for="t in toasts"
                :key="t.id"
                class="pointer-events-auto flex items-start justify-between gap-3 rounded-xl px-4 py-3 shadow-lg"
                :class="toneClass(t.type)"
            >
                <span class="text-sm leading-snug">{{ t.message }}</span>
                <button class="shrink-0 text-sm opacity-80 transition hover:opacity-100" @click="dismiss(t.id)">✕</button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.25s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(1rem);
}
</style>
