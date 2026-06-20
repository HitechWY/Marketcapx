<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import WalletList from '@/Components/User/WalletList.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallets: DashboardWallet[] }>();
</script>

<template>
    <Head title="All Wallets" />

    <UserLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Wallets</h1>
            <WalletList :wallets="wallets" />
        </div>
    </UserLayout>
</template>
