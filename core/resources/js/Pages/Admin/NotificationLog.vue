<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import type { AdminGuardSummary, AdminNotificationLogRow } from '@/types/admin';
import type { Paginator } from '@/types/public';

// F13a: read-only per-user notification log. Metadata only — there is deliberately no
// message body here and no v-html anywhere (the raw HTML body is never sent as a prop).
defineProps<{
    pageTitle: string;
    user: AdminGuardSummary;
    logs: Paginator<AdminNotificationLogRow>;
}>();
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-md-on-background">{{ pageTitle }}</h1>
                    <p class="text-sm text-md-on-surface-variant">{{ user.fullname }} · @{{ user.username }} · {{ user.userType }}</p>
                </div>
            </div>

            <AdminTable :headers="['Subject', 'Channel', 'Type', 'Sent To', 'Status', 'Sent At']" :links="logs.links">
                <tr v-if="!logs.data.length">
                    <td colspan="6" class="px-4 py-8 text-center text-md-on-surface-variant">No notifications found.</td>
                </tr>
                <tr v-for="log in logs.data" :key="log.id" class="transition hover:bg-md-on-surface/5">
                    <td class="px-4 py-3 font-medium text-md-on-surface">{{ log.subject || '—' }}</td>
                    <td class="px-4 py-3">{{ log.channel || '—' }}</td>
                    <td class="px-4 py-3">{{ log.type || '—' }}</td>
                    <td class="px-4 py-3">{{ log.sentTo || '—' }}</td>
                    <td class="px-4 py-3">
                        <span :class="log.read ? 'text-md-primary' : 'text-md-on-surface-variant'">{{ log.read ? 'Read' : 'Unread' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <p>{{ log.date }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ log.dateHuman }}</p>
                    </td>
                </tr>
            </AdminTable>
        </div>
    </AdminLayout>
</template>
