<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MerchantLayout from '@/Layouts/MerchantLayout.vue';
import PanelWallets from '@/Components/Panel/PanelWallets.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallets: DashboardWallet[] }>();
</script>

<template>
    <Head title="All Wallets" />

    <MerchantLayout>
        <PanelWallets :wallets="wallets" />
    </MerchantLayout>
</template>
