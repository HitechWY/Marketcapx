<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';

// F0 proof-of-concept page. Renders only at /poc/inertia. Proves: Vue mounts,
// TypeScript compiles (typed Inertia props below), Tailwind utilities apply,
// the MD3 token bridge + light/dark theme work, and Laravel->Inertia props flow.
defineProps<{
    message: string;
    framework: string;
    laravelVersion: string;
}>();

const theme = ref<string>(
    (typeof document !== 'undefined' && document.documentElement.getAttribute('data-md-theme')) || 'dark',
);

function toggleTheme(): void {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-md-theme', theme.value);
    document.cookie = `md_theme=${theme.value};path=/;max-age=31536000;samesite=lax`;
    try {
        localStorage.setItem('md-theme', theme.value);
    } catch {
        /* ignore storage failures (private mode etc.) */
    }
}
</script>

<template>
    <Head title="Inertia PoC" />

    <main class="flex min-h-screen items-center justify-center bg-md-background p-6 text-md-on-background">
        <section
            class="w-full max-w-md rounded-3xl bg-md-surface p-8 shadow-xl ring-1 ring-md-outline/20"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-md-primary">
                {{ framework }}
            </p>

            <h1 class="mt-3 text-2xl font-semibold leading-tight">
                {{ message }}
            </h1>

            <p class="mt-4 text-sm opacity-80">
                Laravel {{ laravelVersion }} · Vue 3 · TypeScript · Vite · Tailwind v4 · Inertia
            </p>

            <div class="mt-6 flex items-center gap-3 rounded-xl bg-md-surface-container p-4">
                <span class="inline-flex h-4 w-4 rounded-full bg-md-primary"></span>
                <span class="text-sm">MD3 brand token (primary) — tracks the live theme</span>
            </div>

            <button
                type="button"
                class="mt-6 rounded-full bg-md-primary px-6 py-2.5 text-sm font-medium text-md-on-primary transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-md-primary focus-visible:ring-offset-2"
                @click="toggleTheme"
            >
                Toggle theme — now: {{ theme }}
            </button>
        </section>
    </main>
</template>
