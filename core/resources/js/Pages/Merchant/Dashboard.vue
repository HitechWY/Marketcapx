<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MerchantLayout from '@/Layouts/MerchantLayout.vue';
import PanelDashboard from '@/Components/Panel/PanelDashboard.vue';
import type { DashboardWallet, TotalBalance, DashboardKyc, DashboardTransaction } from '@/types/user';

defineProps<{
    wallets: DashboardWallet[];
    totalBalance: TotalBalance;
    insight: { in: number; out: number };
    kyc: DashboardKyc;
    histories: DashboardTransaction[];
    walletsUrl: string | null;
    insightUrl: string | null;
}>();
</script>

<template>
    <Head title="Merchant Dashboard" />
    <MerchantLayout>
        <PanelDashboard
            :wallets="wallets"
            :total-balance="totalBalance"
            :insight="insight"
            :kyc="kyc"
            :histories="histories"
            :wallets-url="walletsUrl"
            :insight-url="insightUrl"
        />
    </MerchantLayout>
</template>
