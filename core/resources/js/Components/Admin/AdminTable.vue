<script setup lang="ts">
import Pagination from '@/Components/Public/Pagination.vue';
import type { PaginatorLink } from '@/types/public';

// Read-only admin table shell: header row + tbody slot + pagination. Rows (incl. the
// empty-state row) are provided by the page via the default slot.
defineProps<{ headers: string[]; links: PaginatorLink[] }>();
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-hidden rounded-2xl bg-md-surface ring-1 ring-md-outline-variant/40">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-md-outline-variant/40 text-xs uppercase tracking-wide text-md-on-surface-variant">
                        <tr>
                            <th v-for="h in headers" :key="h" class="whitespace-nowrap px-4 py-3 font-semibold">{{ h }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-md-outline-variant/30 text-md-on-surface">
                        <slot />
                    </tbody>
                </table>
            </div>
        </div>
        <Pagination :links="links" />
    </div>
</template>
