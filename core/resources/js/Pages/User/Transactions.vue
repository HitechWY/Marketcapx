<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import FilterBar from '@/Components/User/FilterBar.vue';
import TransactionTable from '@/Components/User/TransactionTable.vue';
import Pagination from '@/Components/Public/Pagination.vue';
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
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Transactions</h1>
            <FilterBar :filters="filters" :currencies="currencies" />
            <TransactionTable :transactions="transactions.data" empty-message="No transactions found." />
            <Pagination :links="transactions.links" />
        </div>
    </UserLayout>
</template>
