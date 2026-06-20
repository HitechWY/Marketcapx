<script setup lang="ts">
import WalletList from '@/Components/User/WalletList.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallets: DashboardWallet[] }>();
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-md-on-background">Wallets</h1>
        <WalletList :wallets="wallets" />
    </div>
</template>
