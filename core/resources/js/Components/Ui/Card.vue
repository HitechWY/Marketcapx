<script setup lang="ts">
// MD3 surface card. `interactive` adds a subtle hover lift for clickable cards.
withDefaults(
    defineProps<{
        interactive?: boolean;
        padded?: boolean;
    }>(),
    {
        interactive: false,
        padded: true,
    },
);
</script>

<template>
    <div
        class="rounded-3xl bg-md-surface ring-1 ring-md-outline-variant/40 transition"
        :class="[
            padded ? 'p-6 sm:p-8' : '',
            interactive ? 'hover:-translate-y-1 hover:ring-md-primary/40 hover:shadow-lg' : 'shadow-sm',
        ]"
    >
        <slot />
    </div>
</template>
