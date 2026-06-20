<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import PanelTransactions from '@/Components/Panel/PanelTransactions.vue';
import type { DashboardTransaction, TxFilters } from '@/types/user';
import type { Paginator } from '@/types/public';

defineProps<{
    transactions: Paginator<DashboardTransaction>;
    filters: TxFilters;
    currencies: string[];
}>();
</script>

<template>
    <Head title="Transaction History" />

    <AgentLayout>
        <PanelTransactions :transactions="transactions" :filters="filters" :currencies="currencies" />
    </AgentLayout>
</template>
