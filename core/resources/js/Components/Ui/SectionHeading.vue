<script setup lang="ts">
// Reusable section heading: optional eyebrow, title, and subtitle, with alignment.
withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        subtitle?: string;
        align?: 'left' | 'center';
    }>(),
    {
        eyebrow: undefined,
        subtitle: undefined,
        align: 'left',
    },
);
</script>

<template>
    <div :class="align === 'center' ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl'">
        <p
            v-if="eyebrow"
            class="text-xs font-semibold uppercase tracking-[0.2em] text-md-primary"
        >
            {{ eyebrow }}
        </p>
        <h2 class="mt-3 text-3xl font-semibold leading-tight text-md-on-background sm:text-4xl">
            {{ title }}
        </h2>
        <p v-if="subtitle" class="mt-4 text-base leading-relaxed text-md-on-surface-variant">
            {{ subtitle }}
        </p>
    </div>
</template>
