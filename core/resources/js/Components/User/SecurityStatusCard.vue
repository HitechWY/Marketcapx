<script setup lang="ts">
import Icon from '@/Components/Ui/Icon.vue';

defineProps<{ enabled: boolean }>();
</script>

<template>
    <div class="flex items-center gap-3 rounded-2xl bg-md-surface-container p-4">
        <span
            class="inline-flex h-10 w-10 items-center justify-center rounded-full"
            :class="enabled ? 'bg-emerald-500/15 text-emerald-500' : 'bg-md-error/15 text-md-error'"
        >
            <Icon name="shield" />
        </span>
        <div>
            <p class="text-sm font-semibold text-md-on-surface">Two-factor authentication</p>
            <p class="text-xs" :class="enabled ? 'text-emerald-500' : 'text-md-on-surface-variant'">
                {{ enabled ? 'Enabled' : 'Disabled' }}
            </p>
        </div>
    </div>
</template>
