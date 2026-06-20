<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
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

    <UserLayout>
        <PanelTransactions :transactions="transactions" :filters="filters" :currencies="currencies" />
    </UserLayout>
</template>
