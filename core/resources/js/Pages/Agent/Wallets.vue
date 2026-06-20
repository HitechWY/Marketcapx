<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import PanelWallets from '@/Components/Panel/PanelWallets.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallets: DashboardWallet[] }>();
</script>

<template>
    <Head title="All Wallets" />

    <AgentLayout>
        <PanelWallets :wallets="wallets" />
    </AgentLayout>
</template>
