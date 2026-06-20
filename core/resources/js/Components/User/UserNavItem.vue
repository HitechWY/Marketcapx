<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Ui/Icon.vue';
import type { NavItem } from '@/types/user';

// Renders a nav link. Uses a plain <a> (most targets are still Blade in F2a; Inertia
// detects non-Inertia responses and full-loads). Active when the current path matches.
const props = defineProps<{ item: NavItem }>();

const page = usePage();

function pathOf(url: string): string {
    try {
        return new URL(url, 'http://x').pathname.replace(/\/$/, '');
    } catch {
        return url;
    }
}

const active = computed(() => {
    const current = (page.url || '').split('?')[0].replace(/\/$/, '');
    return pathOf(props.item.url) === current;
});
</script>

<template>
    <a
        :href="item.url"
        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
        :class="active
            ? 'bg-md-primary text-md-on-primary'
            : 'text-md-on-surface-variant hover:bg-md-on-surface/5 hover:text-md-on-surface'"
    >
        <Icon :name="item.icon" size="1.25rem" />
        <span>{{ item.label }}</span>
    </a>
</template>
