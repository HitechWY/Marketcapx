<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import PanelWallets from '@/Components/Panel/PanelWallets.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallets: DashboardWallet[] }>();
</script>

<template>
    <Head title="All Wallets" />

    <UserLayout>
        <PanelWallets :wallets="wallets" />
    </UserLayout>
</template>
