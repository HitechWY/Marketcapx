<script setup lang="ts">
import PanelShell from '@/Components/PanelShell.vue';

// Admin read-only Inertia shell (F6). Reuses the guard-agnostic PanelShell (topbar +
// sidebar + flash) — admin auth.user/nav come from the admin branch of HandleInertiaRequests.
</script>

<template>
    <PanelShell>
        <slot />
    </PanelShell>
</template>
