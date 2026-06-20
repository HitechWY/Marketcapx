<script setup lang="ts">
import FlashToasts from '@/Components/Ui/FlashToasts.vue';

// Lightweight guest shell for auth pages (password recovery). Centered card on the
// themed background — deliberately NOT the authenticated PanelShell. Flash notifications
// are read from the shared flash.notify prop (available to guests too).
withDefaults(defineProps<{ brand?: string }>(), { brand: 'MarketCap X' });
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-md-background px-4 py-12 text-md-on-background">
        <div class="w-full max-w-md">
            <div class="mb-6 text-center">
                <a href="/" class="text-2xl font-bold text-md-primary">{{ brand }}</a>
            </div>
            <div class="rounded-2xl bg-md-surface p-6 ring-1 ring-md-outline-variant/40 sm:p-8">
                <slot />
            </div>
        </div>
        <FlashToasts />
    </div>
</template>
