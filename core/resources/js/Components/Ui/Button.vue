<script setup lang="ts">
import { computed } from 'vue';

// MD3-styled button. Renders an <a> when `href` is provided, otherwise a <button>.
// Variants follow Material 3: filled (primary), tonal, outlined, text.
const props = withDefaults(
    defineProps<{
        variant?: 'filled' | 'tonal' | 'outlined' | 'text';
        size?: 'sm' | 'md' | 'lg';
        href?: string;
        type?: 'button' | 'submit' | 'reset';
        block?: boolean;
    }>(),
    {
        variant: 'filled',
        size: 'md',
        href: undefined,
        type: 'button',
        block: false,
    },
);

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-9 px-4 text-sm';
        case 'lg':
            return 'h-13 px-8 text-base';
        default:
            return 'h-11 px-6 text-sm';
    }
});

const variantClasses = computed(() => {
    switch (props.variant) {
        case 'tonal':
            return 'bg-md-primary-container text-md-on-primary-container hover:opacity-90';
        case 'outlined':
            return 'border border-md-outline text-md-on-surface hover:bg-md-on-surface/5';
        case 'text':
            return 'text-md-primary hover:bg-md-primary/10';
        default:
            return 'bg-md-primary text-md-on-primary shadow-sm hover:opacity-90';
    }
});
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :href="href"
        :type="href ? undefined : type"
        class="inline-flex items-center justify-center gap-2 rounded-full font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-md-primary focus-visible:ring-offset-2 focus-visible:ring-offset-md-background"
        :class="[sizeClasses, variantClasses, block ? 'w-full' : '']"
    >
        <slot />
    </component>
</template>
