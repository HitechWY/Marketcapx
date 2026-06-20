<script setup lang="ts">
import WalletCard from '@/Components/User/WalletCard.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallets: DashboardWallet[] }>();
</script>

<template>
    <div v-if="wallets.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <WalletCard v-for="(w, i) in wallets" :key="i" :wallet="w" :show-transfer="!!w.transferUrl" />
    </div>
    <p v-else class="rounded-2xl bg-md-surface p-6 text-md-on-surface-variant ring-1 ring-md-outline-variant/40">
        No wallets yet.
    </p>
</template>
