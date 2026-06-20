<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import AdminFilterBar from '@/Components/Admin/AdminFilterBar.vue';
import type { AdminTransactionRow } from '@/types/admin';
import type { Paginator } from '@/types/public';

const props = defineProps<{
    pageTitle: string;
    transactions: Paginator<AdminTransactionRow>;
    filters: { search: string; user_type: string; currency: string; trx_type: string; remark: string; date: string };
    currencies: { value: number; label: string }[];
    remarks: { value: string; label: string }[];
}>();

const fields = [
    { name: 'search', label: 'TRX / Username', type: 'text' as const, placeholder: 'Search…' },
    { name: 'user_type', label: 'User Type', type: 'select' as const, options: [{ value: 'user', label: 'User' }, { value: 'agent', label: 'Agent' }, { value: 'merchant', label: 'Merchant' }] },
    { name: 'currency', label: 'Currency', type: 'select' as const, options: props.currencies },
    { name: 'trx_type', label: 'Type', type: 'select' as const, options: [{ value: '+', label: 'Plus' }, { value: '-', label: 'Minus' }] },
    { name: 'remark', label: 'Remark', type: 'select' as const, options: props.remarks },
    { name: 'date', label: 'Date (from - to)', type: 'text' as const, placeholder: 'YYYY-MM-DD - YYYY-MM-DD' },
];
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">{{ pageTitle }}</h1>

            <AdminFilterBar :fields="fields" :values="filters" />

            <AdminTable :headers="['User', 'Type', 'TRX', 'Transacted', 'Amount', 'Post Balance', 'Details']" :links="transactions.links">
                <tr v-if="!transactions.data.length">
                    <td colspan="7" class="px-4 py-8 text-center text-md-on-surface-variant">No transactions found.</td>
                </tr>
                <tr v-for="t in transactions.data" :key="t.id" class="transition hover:bg-md-on-surface/5">
                    <td class="px-4 py-3">
                        <p class="font-medium text-md-on-surface">{{ t.fullname }}</p>
                        <p class="text-xs text-md-on-surface-variant">@{{ t.username }}</p>
                    </td>
                    <td class="px-4 py-3">{{ t.userType }}</td>
                    <td class="px-4 py-3 font-mono text-xs">{{ t.trx }}</td>
                    <td class="px-4 py-3">
                        <p>{{ t.date }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ t.dateHuman }}</p>
                    </td>
                    <td class="px-4 py-3 font-semibold" :class="t.trxType === '+' ? 'text-emerald-500' : 'text-red-500'">
                        {{ t.trxType }}{{ t.amount }} {{ t.code }}
                    </td>
                    <td class="px-4 py-3">{{ t.postBalance }} {{ t.code }}</td>
                    <td class="px-4 py-3 text-md-on-surface-variant">{{ t.details }} <span v-if="t.receiver">@{{ t.receiver }}</span></td>
                </tr>
            </AdminTable>
        </div>
    </AdminLayout>
</template>
