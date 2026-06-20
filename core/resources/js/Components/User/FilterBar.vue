<script setup lang="ts">
import { reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type { TxFilters } from '@/types/user';

// Transaction filters — mirrors the Blade select options + query params exactly
// (search, type, operation, time, currency). Navigates the same route via Inertia,
// preserving query semantics (server reads identical params; no backend change).
const props = defineProps<{ filters: TxFilters; currencies: string[] }>();

const base = (usePage().url || '').split('?')[0];
const state = reactive<TxFilters>({ ...props.filters });

const operations = [
    ['', 'All operations'],
    ['add_money', 'Add Money'],
    ['money_out', 'Money Out'],
    ['transfer_money', 'Transfer Money'],
    ['request_money', 'Request Money'],
    ['make_payment', 'Make Payment'],
    ['exchange_money', 'Exchange Money'],
    ['create_voucher', 'Create Voucher'],
    ['withdraw', 'Withdraw Money'],
    ['balance_add', 'Add By System'],
    ['balance_subtract', 'Subtract By System'],
];
const types = [
    ['', 'All types'],
    ['plus_trx', 'Plus Transactions'],
    ['minus_trx', 'Minus Transactions'],
];
const times = [
    ['', 'All time'],
    ['7days', '7 Days'],
    ['15days', '15 Days'],
    ['1month', '1 Month'],
    ['1year', '1 Year'],
];

function apply(): void {
    const query: Record<string, string> = {};
    (Object.entries(state) as Array<[string, string]>).forEach(([k, v]) => {
        if (v) query[k] = v;
    });
    router.get(base, query, { preserveScroll: true, preserveState: true, replace: true });
}

const selectClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-3 py-2 text-sm text-md-on-surface focus:border-md-primary focus:outline-none';
</script>

<template>
    <div class="rounded-2xl bg-md-surface p-4 ring-1 ring-md-outline-variant/40">
        <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="apply">
            <input
                v-model="state.search"
                type="text"
                placeholder="Search by transaction ID"
                class="w-full rounded-xl border border-md-outline-variant bg-md-surface px-3 py-2 text-sm text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none"
            />
            <select v-model="state.type" :class="selectClass" @change="apply">
                <option v-for="[v, l] in types" :key="v" :value="v">{{ l }}</option>
            </select>
            <select v-model="state.operation" :class="selectClass" @change="apply">
                <option v-for="[v, l] in operations" :key="v" :value="v">{{ l }}</option>
            </select>
            <select v-model="state.time" :class="selectClass" @change="apply">
                <option v-for="[v, l] in times" :key="v" :value="v">{{ l }}</option>
            </select>
            <select v-model="state.currency" :class="selectClass" @change="apply">
                <option value="">All currencies</option>
                <option v-for="c in currencies" :key="c" :value="c.toLowerCase()">{{ c }}</option>
            </select>
        </form>
    </div>
</template>
