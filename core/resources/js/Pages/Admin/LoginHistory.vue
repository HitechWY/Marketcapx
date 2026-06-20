<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import AdminFilterBar from '@/Components/Admin/AdminFilterBar.vue';
import type { AdminLoginRow } from '@/types/admin';
import type { Paginator } from '@/types/public';

defineProps<{
    pageTitle: string;
    logins: Paginator<AdminLoginRow>;
    showFilter: boolean;
    filters: { search: string; user_type: string; date: string };
    ip: string | null;
    ipLookupUrl: string | null;
}>();

const fields = [
    { name: 'search', label: 'Username', type: 'text' as const, placeholder: 'Search…' },
    { name: 'user_type', label: 'User Type', type: 'select' as const, options: [{ value: 'user', label: 'User' }, { value: 'agent', label: 'Agent' }, { value: 'merchant', label: 'Merchant' }] },
    { name: 'date', label: 'Date (from - to)', type: 'text' as const, placeholder: 'YYYY-MM-DD - YYYY-MM-DD' },
];
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold text-md-on-background">{{ pageTitle }}</h1>
                <a v-if="ipLookupUrl" :href="ipLookupUrl" target="_blank" class="rounded-xl bg-md-surface px-4 py-2 text-sm font-medium text-md-primary ring-1 ring-md-outline-variant/40 hover:bg-md-on-surface/5">Lookup IP {{ ip }}</a>
            </div>

            <AdminFilterBar v-if="showFilter" :fields="fields" :values="filters" />

            <AdminTable :headers="['User', 'Type', 'Login At', 'IP', 'Location', 'Browser · OS']" :links="logins.links">
                <tr v-if="!logins.data.length">
                    <td colspan="6" class="px-4 py-8 text-center text-md-on-surface-variant">No login records found.</td>
                </tr>
                <tr v-for="l in logins.data" :key="l.id" class="transition hover:bg-md-on-surface/5">
                    <td class="px-4 py-3">
                        <p class="font-medium text-md-on-surface">{{ l.fullname }}</p>
                        <p class="text-xs text-md-on-surface-variant">@{{ l.username }}</p>
                    </td>
                    <td class="px-4 py-3">{{ l.userType }}</td>
                    <td class="px-4 py-3">
                        <p>{{ l.date }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ l.dateHuman }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <a :href="l.ipUrl" class="text-md-primary hover:underline">{{ l.ip }}</a>
                    </td>
                    <td class="px-4 py-3">{{ l.city }}<br /><span class="text-xs text-md-on-surface-variant">{{ l.country }}</span></td>
                    <td class="px-4 py-3">{{ l.browser }}<br /><span class="text-xs text-md-on-surface-variant">{{ l.os }}</span></td>
                </tr>
            </AdminTable>
        </div>
    </AdminLayout>
</template>
