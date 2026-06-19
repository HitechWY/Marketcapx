<script setup lang="ts">
import { ref } from 'vue';
import { route } from 'ziggy-js';
import { useTheme } from '@/composables/useTheme';
import Button from '@/Components/Ui/Button.vue';

// Public marketing header. Nav links point at the existing LIVE Blade routes
// (resolved via the scoped Ziggy 'public' group), so this header works as a
// preview shell without replacing any page.
withDefaults(defineProps<{ brand?: string }>(), { brand: 'MarketCap X' });

const { theme, toggleTheme } = useTheme();
const mobileOpen = ref(false);

const nav: Array<{ label: string; href: string }> = [
    { label: 'Home', href: route('home') },
    { label: 'Announcements', href: route('blogs') },
    { label: 'Contact', href: route('contact') },
];
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-md-outline-variant/40 bg-md-background/80 backdrop-blur-md"
    >
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            <!-- Brand -->
            <a :href="route('home')" class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-md-primary text-md-on-primary">
                    <span class="text-sm font-bold">M</span>
                </span>
                <span class="text-lg font-semibold tracking-tight text-md-on-background">{{ brand }}</span>
            </a>

            <!-- Desktop nav -->
            <nav class="hidden items-center gap-8 md:flex">
                <a
                    v-for="item in nav"
                    :key="item.label"
                    :href="item.href"
                    class="text-sm font-medium text-md-on-surface-variant transition hover:text-md-primary"
                >
                    {{ item.label }}
                </a>
            </nav>

            <div class="flex items-center gap-2">
                <!-- Theme toggle -->
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-md-on-surface-variant transition hover:bg-md-on-surface/5"
                    :aria-label="`Switch to ${theme === 'dark' ? 'light' : 'dark'} theme`"
                    @click="toggleTheme"
                >
                    <span class="text-lg" aria-hidden="true">{{ theme === 'dark' ? '☀' : '☾' }}</span>
                </button>

                <Button :href="route('contact')" size="sm" class="hidden sm:inline-flex">Get in touch</Button>

                <!-- Mobile menu toggle -->
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-md-on-surface-variant transition hover:bg-md-on-surface/5 md:hidden"
                    aria-label="Toggle menu"
                    @click="mobileOpen = !mobileOpen"
                >
                    <span class="text-xl" aria-hidden="true">{{ mobileOpen ? '✕' : '☰' }}</span>
                </button>
            </div>
        </div>

        <!-- Mobile nav panel -->
        <div v-if="mobileOpen" class="border-t border-md-outline-variant/40 bg-md-surface md:hidden">
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3">
                <a
                    v-for="item in nav"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-xl px-3 py-2 text-sm font-medium text-md-on-surface-variant transition hover:bg-md-on-surface/5 hover:text-md-primary"
                >
                    {{ item.label }}
                </a>
            </nav>
        </div>
    </header>
</template>
