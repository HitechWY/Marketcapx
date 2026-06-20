<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Pagination from '@/Components/Public/Pagination.vue';
import type { WithdrawRow } from '@/types/user';
import type { Paginator } from '@/types/public';

const props = defineProps<{ withdraws: Paginator<WithdrawRow>; search: string }>();

const base = (usePage().url || '').split('?')[0];
const term = ref(props.search);

function doSearch(): void {
    router.get(base, term.value ? { search: term.value } : {}, { preserveScroll: true, replace: true });
}
</script>

<template>
    <Head title="Withdraw History" />

    <UserLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Withdraw History</h1>

            <form class="flex gap-2" @submit.prevent="doSearch">
                <input
                    v-model="term"
                    type="text"
                    placeholder="Search by transaction ID"
                    class="w-full max-w-sm rounded-xl border border-md-outline-variant bg-md-surface px-3 py-2 text-sm text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none"
                />
                <button type="submit" class="rounded-xl bg-md-primary px-4 py-2 text-sm font-medium text-md-on-primary">Search</button>
            </form>

            <div class="overflow-hidden rounded-2xl bg-md-surface ring-1 ring-md-outline-variant/40">
                <div v-if="!withdraws.data.length" class="p-8 text-center text-md-on-surface-variant">No withdrawals found.</div>
                <ul v-else class="divide-y divide-md-outline-variant/40">
                    <li v-for="(w, i) in withdraws.data" :key="i" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-md-on-surface">{{ w.method }}</p>
                            <p class="text-xs text-md-on-surface-variant">{{ w.trx }} · {{ w.dateHuman }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-md-on-surface">{{ w.symbol }}{{ w.afterCharge }} {{ w.code }}</p>
                            <p class="text-xs text-md-on-surface-variant">-{{ w.charge }} charge</p>
                        </div>
                        <span class="shrink-0" v-html="w.statusBadge"></span>
                    </li>
                </ul>
            </div>

            <Pagination :links="withdraws.links" />
        </div>
    </UserLayout>
</template>
