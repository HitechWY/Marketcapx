<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Pagination from '@/Components/Public/Pagination.vue';
import type { DepositRow } from '@/types/user';
import type { Paginator } from '@/types/public';

// Shared read-only deposit-history content for any panel. `searchable` toggles the
// trx-search box (user deposit history supports it; agent add-money history does not).
const props = withDefaults(
    defineProps<{ deposits: Paginator<DepositRow>; search?: string; searchable?: boolean }>(),
    { search: '', searchable: true },
);

const base = (usePage().url || '').split('?')[0];
const term = ref(props.search);
const expanded = ref<number | null>(null);

function doSearch(): void {
    router.get(base, term.value ? { search: term.value } : {}, { preserveScroll: true, replace: true });
}
</script>

<template>
    <div class="space-y-6">
        <h1 class="text-2xl font-semibold text-md-on-background">Deposit History</h1>

        <form v-if="searchable" class="flex gap-2" @submit.prevent="doSearch">
            <input
                v-model="term"
                type="text"
                placeholder="Search by transaction ID"
                class="w-full max-w-sm rounded-xl border border-md-outline-variant bg-md-surface px-3 py-2 text-sm text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none"
            />
            <button type="submit" class="rounded-xl bg-md-primary px-4 py-2 text-sm font-medium text-md-on-primary">Search</button>
        </form>

        <div class="overflow-hidden rounded-2xl bg-md-surface ring-1 ring-md-outline-variant/40">
            <div v-if="!deposits.data.length" class="p-8 text-center text-md-on-surface-variant">No deposits found.</div>
            <ul v-else class="divide-y divide-md-outline-variant/40">
                <li v-for="(d, i) in deposits.data" :key="i" class="px-4 py-3.5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-md-on-surface">{{ d.gateway }}</p>
                            <p class="text-xs text-md-on-surface-variant">{{ d.trx }} · {{ d.dateHuman }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-md-on-surface">{{ d.symbol }}{{ d.total }} {{ d.code }}</p>
                            <p class="text-xs text-md-on-surface-variant">+{{ d.charge }} charge</p>
                        </div>
                        <span class="shrink-0" v-html="d.statusBadge"></span>
                        <button
                            v-if="d.isManual"
                            type="button"
                            class="rounded-lg bg-md-surface-container px-3 py-1.5 text-xs font-medium text-md-on-surface transition hover:bg-md-on-surface/5"
                            @click="expanded = expanded === i ? null : i"
                        >
                            Details
                        </button>
                    </div>

                    <div v-if="d.isManual && expanded === i" class="mt-3 rounded-xl bg-md-surface-container/50 p-3 text-sm">
                        <p v-if="d.adminFeedback" class="mb-2 text-md-error">{{ d.adminFeedback }}</p>
                        <dl class="grid gap-2 sm:grid-cols-2">
                            <div v-for="(info, k) in d.detail" :key="k" class="flex justify-between gap-4">
                                <dt class="text-md-on-surface-variant">{{ info.name }}</dt>
                                <dd class="font-medium text-md-on-surface">
                                    <a v-if="info.type === 'file' && info.value" :href="info.value" class="text-md-primary hover:underline">Download</a>
                                    <span v-else>{{ info.value }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                </li>
            </ul>
        </div>

        <Pagination :links="deposits.links" />
    </div>
</template>
