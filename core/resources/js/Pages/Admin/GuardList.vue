<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminTable from '@/Components/Admin/AdminTable.vue';
import AdminFilterBar from '@/Components/Admin/AdminFilterBar.vue';
import type { AdminGuardRow } from '@/types/admin';
import type { Paginator } from '@/types/public';

defineProps<{
    guardType: string;
    pageTitle: string;
    users: Paginator<AdminGuardRow>;
    filters: { search: string };
    showKyc: boolean;
}>();
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">{{ pageTitle }}</h1>

            <AdminFilterBar
                :fields="[{ name: 'search', label: 'Username / Email', type: 'text', placeholder: 'Search…' }]"
                :values="filters"
            />

            <AdminTable :headers="['User', 'Email · Mobile', 'Country', 'Joined', 'Action']" :links="users.links">
                <tr v-if="!users.data.length">
                    <td colspan="5" class="px-4 py-8 text-center text-md-on-surface-variant">No records found.</td>
                </tr>
                <tr v-for="u in users.data" :key="u.id" class="transition hover:bg-md-on-surface/5">
                    <td class="px-4 py-3">
                        <p class="font-medium text-md-on-surface">{{ u.fullname }}</p>
                        <a :href="u.detailUrl" class="text-xs text-md-primary hover:underline">@{{ u.username }}</a>
                    </td>
                    <td class="px-4 py-3">
                        <p>{{ u.email }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ u.mobile }}</p>
                    </td>
                    <td class="px-4 py-3"><span :title="u.countryName ?? ''">{{ u.countryCode }}</span></td>
                    <td class="px-4 py-3">
                        <p>{{ u.joined }}</p>
                        <p class="text-xs text-md-on-surface-variant">{{ u.joinedHuman }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <a :href="u.detailUrl" class="rounded-lg bg-md-surface-container px-3 py-1.5 text-xs font-medium text-md-on-surface transition hover:bg-md-on-surface/5">Details</a>
                            <a v-if="showKyc && u.kycDataUrl" :href="u.kycDataUrl" target="_blank" class="rounded-lg bg-md-surface-container px-3 py-1.5 text-xs font-medium text-md-on-surface transition hover:bg-md-on-surface/5">KYC Data</a>
                        </div>
                    </td>
                </tr>
            </AdminTable>
        </div>
    </AdminLayout>
</template>
