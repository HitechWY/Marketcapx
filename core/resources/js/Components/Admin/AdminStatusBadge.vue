<script setup lang="ts">
import { computed } from 'vue';

// Tone-based MD3 chip for admin lists (no raw HTML; tone/label computed server-side).
const props = withDefaults(defineProps<{ label: string; tone?: string }>(), { tone: 'muted' });

const toneClass = computed(() => {
    switch (props.tone) {
        case 'success':
            return 'bg-emerald-500/15 text-emerald-500';
        case 'warning':
            return 'bg-amber-500/15 text-amber-500';
        case 'danger':
            return 'bg-red-500/15 text-red-500';
        case 'info':
            return 'bg-sky-500/15 text-sky-500';
        default:
            return 'bg-md-on-surface/10 text-md-on-surface-variant';
    }
});
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="toneClass">{{ label }}</span>
</template>
