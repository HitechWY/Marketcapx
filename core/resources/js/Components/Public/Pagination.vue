<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { PaginatorLink } from '@/types/public';

// Renders Laravel paginator links (Previous / 1 2 3 / Next). Hidden when there is
// only one page (Laravel returns exactly 3 links: Previous, "1", Next). Links carry
// HTML entities (&laquo;) so labels use v-html. Inertia <Link> keeps it SPA.
defineProps<{ links: PaginatorLink[] }>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-1">
        <template v-for="(link, i) in links" :key="i">
            <span
                v-if="!link.url"
                class="px-3 py-2 text-sm text-md-on-surface-variant/40"
                v-html="link.label"
            ></span>
            <Link
                v-else
                :href="link.url"
                class="rounded-lg px-3.5 py-2 text-sm transition"
                :class="link.active
                    ? 'bg-md-primary text-md-on-primary'
                    : 'text-md-on-surface-variant hover:bg-md-on-surface/5'"
                v-html="link.label"
            ></Link>
        </template>
    </nav>
</template>
