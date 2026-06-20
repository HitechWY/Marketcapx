<script setup lang="ts">
import Icon from '@/Components/Ui/Icon.vue';

// Read-only metric tile for the admin dashboard.
withDefaults(defineProps<{ label: string; value: string | number; icon?: string; hint?: string }>(), {
    icon: 'dashboard',
    hint: '',
});
</script>

<template>
    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
        <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-md-on-surface-variant">{{ label }}</p>
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-md-primary/10 text-md-primary">
                <Icon :name="icon" size="1.15rem" />
            </span>
        </div>
        <p class="mt-2 text-2xl font-semibold text-md-on-surface">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs text-md-on-surface-variant">{{ hint }}</p>
    </div>
</template>
