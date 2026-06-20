<script setup lang="ts">
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import UserTopbar from '@/Components/User/UserTopbar.vue';
import UserSidebar from '@/Components/User/UserSidebar.vue';
import FlashToasts from '@/Components/Ui/FlashToasts.vue';
import type { AuthUser, UserNav } from '@/types/user';

// Shared authenticated shell for the user / merchant / agent panels. Reads the
// guard-aware shared props (auth.user, nav) set by HandleInertiaRequests, so the
// same shell serves every panel.
const page = usePage();
const props = page.props as Record<string, any>;
const user = (props.auth?.user ?? null) as AuthUser | null;
const nav = props.nav as UserNav;

const sidebarOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-md-background text-md-on-background">
        <UserTopbar :user="user" :nav="nav" @toggle-sidebar="sidebarOpen = !sidebarOpen" />

        <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6 sm:px-6">
            <aside class="hidden w-64 shrink-0 lg:block">
                <div class="sticky top-20">
                    <UserSidebar :nav="nav" />
                </div>
            </aside>

            <div v-if="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
                <div class="absolute inset-0 bg-black/50" @click="sidebarOpen = false"></div>
                <div class="absolute left-0 top-0 h-full w-72 max-w-[80vw] overflow-y-auto bg-md-surface p-4 shadow-xl">
                    <UserSidebar :nav="nav" />
                </div>
            </div>

            <main class="min-w-0 flex-1">
                <slot />
            </main>
        </div>

        <FlashToasts />
    </div>
</template>
