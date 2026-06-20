<script setup lang="ts">
import FilterBar from '@/Components/User/FilterBar.vue';
import TransactionTable from '@/Components/User/TransactionTable.vue';
import Pagination from '@/Components/Public/Pagination.vue';
import type { DashboardTransaction, TxFilters } from '@/types/user';
import type { Paginator } from '@/types/public';

// Shared read-only transactions content (filters + table + pagination) for any panel.
defineProps<{
    transactions: Paginator<DashboardTransaction>;
    filters: TxFilters;
    currencies: string[];
}>();
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-md-on-background">Transactions</h1>
        <FilterBar :filters="filters" :currencies="currencies" />
        <TransactionTable :transactions="transactions.data" empty-message="No transactions found." />
        <Pagination :links="transactions.links" />
    </div>
</template>
