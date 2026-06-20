<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import AdminFilterBar from '@/Components/Admin/AdminFilterBar.vue';
import AdminStatusBadge from '@/Components/Admin/AdminStatusBadge.vue';
import AdminMetricCard from '@/Components/Admin/AdminMetricCard.vue';
import type { AdminDepositRow } from '@/types/admin';
import type { Paginator } from '@/types/public';

const props = defineProps<{
    pageTitle: string;
    deposits: Paginator<AdminDepositRow>;
    summary: { successful: string; pending: string; rejected: string; initiated: string } | null;
    filters: { search: string; user_type: string; currency: string; date: string };
    currencies: { value: number; label: string }[];
    currencySymbol: string;
}>();

const fields = [
    { name: 'search', label: 'TRX / Username', type: 'text' as const, placeholder: 'Search…' },
    { name: 'user_type', label: 'User Type', type: 'select' as const, options: [{ value: 'user', label: 'User' }, { value: 'agent', label: 'Agent' }] },
    { name: 'currency', label: 'Currency', type: 'select' as const, options: props.currencies },
    { name: 'date', label: 'Date (from - to)', type: 'text' as const, placeholder: 'YYYY-MM-DD - YYYY-MM-DD' },
];
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">{{ pageTitle }}</h1>

            <div v-if="summary" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AdminMetricCard label="Successful" :value="`${currencySymbol}${summary.successful}`" icon="receipt" />
                <AdminMetricCard label="Pending" :value="`${currencySymbol}${summary.pending}`" icon="receipt" />
                <AdminMetricCard label="Rejected" :value="`${currencySymbol}${summary.rejected}`" icon="receipt" />
                <AdminMetricCard label="Initiated" :value="`${currencySymbol}${summary.initiated}`" icon="receipt" />
            </div>

            <AdminFilterBar :fields="fields" :values="filters" />

            <AdminTable :headers="['Gateway · TRX', 'Initiated', 'User Type', 'User', 'Amount', 'Status']" :links="deposits.links">
                <tr v-if="!deposits.data.length">
                    <td colspan="6" class="px-4 py-8 text-center text-md-on-surface-variant">No deposits found.</td>
                </tr>
                <tr v-for="d in deposits.data" :key="d.id" class="transition hover:bg-md-on-surface/5">
                    <td class="px-4 py-3">
                        <p class="font-medium text-md-on-surface">{{ d.gateway }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ d.trx }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p>{{ d.initiated }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ d.initiatedHuman }}</p>
                    </td>
                    <td class="px-4 py-3">{{ d.userType }}</td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-md-on-surface">{{ d.fullname }}</p>
                        <p class="text-xs text-md-on-surface-variant">@{{ d.username }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-md-on-surface">{{ d.total }} {{ d.currency }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ d.amount }} + {{ d.charge }} charge</p>
                    </td>
                    <td class="px-4 py-3"><AdminStatusBadge :label="d.status.label" :tone="d.status.tone" /></td>
                </tr>
            </AdminTable>

            <p class="text-xs text-md-on-surface-variant">Approve/reject actions remain on the (Blade) deposit detail page.</p>
        </div>
    </AdminLayout>
</template>
