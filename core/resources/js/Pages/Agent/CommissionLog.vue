<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import TransactionTable from '@/Components/User/TransactionTable.vue';
import Pagination from '@/Components/Public/Pagination.vue';
import type { DashboardTransaction } from '@/types/user';
import type { Paginator } from '@/types/public';

defineProps<{ transactions: Paginator<DashboardTransaction> }>();
</script>

<template>
    <Head title="Commission Log" />

    <AgentLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Commission Log</h1>
            <TransactionTable :transactions="transactions.data" empty-message="No commission found." />
            <Pagination :links="transactions.links" />
        </div>
    </AgentLayout>
</template>
