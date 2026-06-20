<script setup lang="ts">
import UserNavItem from '@/Components/User/UserNavItem.vue';
import type { UserNav } from '@/types/user';

defineProps<{ nav: UserNav }>();
</script>

<template>
    <nav class="flex flex-col gap-6">
        <div>
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-md-on-surface-variant/60">Menu</p>
            <div class="flex flex-col gap-1">
                <UserNavItem v-for="item in nav.primary" :key="item.url" :item="item" />
            </div>
        </div>

        <div v-if="nav.actions.length">
            <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wider text-md-on-surface-variant/60">Money</p>
            <div class="flex flex-col gap-1">
                <UserNavItem v-for="item in nav.actions" :key="item.url" :item="item" />
            </div>
        </div>
    </nav>
</template>
