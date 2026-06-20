<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/Components/Ui/Icon.vue';
import { useTheme } from '@/composables/useTheme';
import type { AuthUser, UserNav } from '@/types/user';

defineProps<{ user: AuthUser | null; nav: UserNav }>();
const emit = defineEmits<{ (e: 'toggle-sidebar'): void }>();

const { theme, toggleTheme } = useTheme();
const menuOpen = ref(false);
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-md-outline-variant/40 bg-md-background/80 backdrop-blur-md">
        <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-md-on-surface-variant transition hover:bg-md-on-surface/5 lg:hidden"
                    aria-label="Toggle menu"
                    @click="emit('toggle-sidebar')"
                >
                    <Icon name="menu" />
                </button>
                <a href="/" class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-md-primary text-md-on-primary">
                        <span class="text-sm font-bold">M</span>
                    </span>
                    <span class="hidden text-lg font-semibold tracking-tight text-md-on-background sm:inline">MarketCap X</span>
                </a>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-md-on-surface-variant transition hover:bg-md-on-surface/5"
                    :aria-label="`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`"
                    @click="toggleTheme"
                >
                    <Icon :name="theme === 'dark' ? 'sun' : 'moon'" />
                </button>

                <div class="relative">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 transition hover:bg-md-on-surface/5"
                        @click="menuOpen = !menuOpen"
                    >
                        <img
                            v-if="user?.image"
                            :src="user.image"
                            alt=""
                            class="h-8 w-8 rounded-full object-cover ring-1 ring-md-outline-variant/50"
                        />
                        <span v-else class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-md-surface-container text-md-on-surface-variant">
                            <Icon name="profile" />
                        </span>
                        <span class="hidden text-sm font-medium text-md-on-surface sm:inline">{{ user?.fullname }}</span>
                    </button>

                    <div
                        v-if="menuOpen"
                        class="absolute right-0 mt-2 w-56 overflow-hidden rounded-2xl bg-md-surface p-1.5 shadow-xl ring-1 ring-md-outline-variant/40"
                        @click="menuOpen = false"
                    >
                        <a
                            v-for="item in nav.account"
                            :key="item.url"
                            :href="item.url"
                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm text-md-on-surface-variant transition hover:bg-md-on-surface/5 hover:text-md-on-surface"
                        >
                            <Icon :name="item.icon" size="1.15rem" />
                            <span>{{ item.label }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
