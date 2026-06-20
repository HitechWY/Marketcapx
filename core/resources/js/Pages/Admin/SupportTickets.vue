<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import AdminFilterBar from '@/Components/Admin/AdminFilterBar.vue';
import AdminStatusBadge from '@/Components/Admin/AdminStatusBadge.vue';
import type { AdminTicketRow } from '@/types/admin';
import type { Paginator } from '@/types/public';

defineProps<{
    pageTitle: string;
    tickets: Paginator<AdminTicketRow>;
    filters: { search: string };
}>();
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">{{ pageTitle }}</h1>

            <AdminFilterBar
                :fields="[{ name: 'search', label: 'Search', type: 'text', placeholder: 'Subject / name / ticket…' }]"
                :values="filters"
            />

            <AdminTable :headers="['Subject', 'Submitted By', 'User Type', 'Status', 'Priority', 'Last Reply', 'Action']" :links="tickets.links">
                <tr v-if="!tickets.data.length">
                    <td colspan="7" class="px-4 py-8 text-center text-md-on-surface-variant">No tickets found.</td>
                </tr>
                <tr v-for="t in tickets.data" :key="t.id" class="transition hover:bg-md-on-surface/5">
                    <td class="px-4 py-3">
                        <a :href="t.viewUrl" class="font-medium text-md-primary hover:underline">[#{{ t.ticket }}] {{ t.subject }}</a>
                    </td>
                    <td class="px-4 py-3">{{ t.name }}</td>
                    <td class="px-4 py-3">{{ t.userType }}</td>
                    <td class="px-4 py-3"><AdminStatusBadge :label="t.status.label" :tone="t.status.tone" /></td>
                    <td class="px-4 py-3"><AdminStatusBadge :label="t.priority.label" :tone="t.priority.tone" /></td>
                    <td class="px-4 py-3 text-md-on-surface-variant">{{ t.lastReplyHuman }}</td>
                    <td class="px-4 py-3">
                        <a :href="t.viewUrl" class="rounded-lg bg-md-surface-container px-3 py-1.5 text-xs font-medium text-md-on-surface transition hover:bg-md-on-surface/5">Details</a>
                    </td>
                </tr>
            </AdminTable>
        </div>
    </AdminLayout>
</template>
