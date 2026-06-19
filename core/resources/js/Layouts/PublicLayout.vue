<script setup lang="ts">
import PublicHeader from '@/Components/Public/PublicHeader.vue';
import PublicFooter from '@/Components/Public/PublicFooter.vue';

// Shared shell for public Inertia pages: themed background + header + content slot
// + footer. Theme-aware via the MD3 token bridge (data-md-theme on <html>).
withDefaults(defineProps<{ brand?: string }>(), { brand: 'MarketCap X' });
</script>

<template>
    <div class="flex min-h-screen flex-col bg-md-background text-md-on-background">
        <PublicHeader :brand="brand" />
        <main class="flex-1">
            <slot />
        </main>
        <PublicFooter :brand="brand" />
    </div>
</template>
