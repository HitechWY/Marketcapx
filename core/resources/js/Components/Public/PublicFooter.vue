<script setup lang="ts">
import { route } from 'ziggy-js';

withDefaults(defineProps<{ brand?: string; year?: number }>(), {
    brand: 'MarketCap X',
    year: 2026,
});

const columns: Array<{ title: string; links: Array<{ label: string; href: string }> }> = [
    {
        title: 'Product',
        links: [
            { label: 'Home', href: route('home') },
            { label: 'Announcements', href: route('blogs') },
        ],
    },
    {
        title: 'Company',
        links: [
            { label: 'Contact', href: route('contact') },
            { label: 'Cookie policy', href: route('cookie.policy') },
        ],
    },
];
</script>

<template>
    <footer class="border-t border-md-outline-variant/40 bg-md-surface-container">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div class="lg:col-span-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-md-primary text-md-on-primary">
                            <span class="text-sm font-bold">M</span>
                        </span>
                        <span class="text-lg font-semibold text-md-on-surface">{{ brand }}</span>
                    </div>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-md-on-surface-variant">
                        A secure digital payments platform. This footer is a preview of the new
                        Inertia/Vue public surface — the live site still runs on the existing pages.
                    </p>
                </div>

                <div v-for="col in columns" :key="col.title">
                    <h3 class="text-sm font-semibold text-md-on-surface">{{ col.title }}</h3>
                    <ul class="mt-4 space-y-3">
                        <li v-for="link in col.links" :key="link.label">
                            <a
                                :href="link.href"
                                class="text-sm text-md-on-surface-variant transition hover:text-md-primary"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-md-outline-variant/40 pt-6 text-sm text-md-on-surface-variant sm:flex-row"
            >
                <p>&copy; {{ year }} {{ brand }}. All rights reserved.</p>
                <p class="text-xs uppercase tracking-widest text-md-primary">Inertia/Vue preview</p>
            </div>
        </div>
    </footer>
</template>
