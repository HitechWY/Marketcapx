<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import { route } from 'ziggy-js';

// F1a public preview page. Props are config()/static only (no DB, no business
// logic). Proves PublicLayout + header/footer + hero + token/theme + Inertia props.
interface Feature {
    icon: string;
    title: string;
    body: string;
}
interface Stat {
    value: string;
    label: string;
}

withDefaults(
    defineProps<{
        brand?: string;
        tagline?: string;
        features?: Feature[];
        stats?: Stat[];
    }>(),
    {
        brand: 'MarketCap X',
        tagline: '',
        features: () => [],
        stats: () => [],
    },
);
</script>

<template>
    <Head title="Public preview" />

    <PublicLayout :brand="brand">
        <!-- Hero -->
        <section class="relative overflow-hidden">
            <div
                class="pointer-events-none absolute inset-x-0 -top-24 h-72 bg-md-primary/10 blur-3xl"
                aria-hidden="true"
            ></div>

            <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
                <div class="mx-auto max-w-3xl text-center">
                    <span
                        class="inline-flex items-center rounded-full bg-md-primary-container px-4 py-1 text-xs font-semibold uppercase tracking-widest text-md-on-primary-container"
                    >
                        Inertia · Vue · Tailwind preview
                    </span>

                    <h1 class="mt-6 text-4xl font-semibold leading-tight text-md-on-background sm:text-6xl">
                        {{ brand }}
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-md-on-surface-variant">
                        {{ tagline }}
                    </p>

                    <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Button :href="route('contact')" size="lg">Get started</Button>
                        <Button :href="route('home')" variant="outlined" size="lg">View live site</Button>
                    </div>
                </div>

                <!-- Stats band -->
                <div v-if="stats.length" class="mx-auto mt-16 grid max-w-3xl grid-cols-3 gap-4">
                    <div v-for="stat in stats" :key="stat.label" class="text-center">
                        <div class="text-3xl font-semibold text-md-primary sm:text-4xl">{{ stat.value }}</div>
                        <div class="mt-1 text-sm text-md-on-surface-variant">{{ stat.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <SectionHeading
                eyebrow="Why MarketCap X"
                title="Everything you need to move money"
                subtitle="A preview of the new public experience, built on the modern frontend stack while the backend stays unchanged."
                align="center"
            />

            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <Card v-for="feature in features" :key="feature.title" interactive>
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-md-primary-container text-2xl">
                        <span aria-hidden="true">{{ feature.icon }}</span>
                    </div>
                    <h3 class="mt-5 text-lg font-semibold text-md-on-surface">{{ feature.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-md-on-surface-variant">{{ feature.body }}</p>
                </Card>
            </div>
        </section>

        <!-- CTA band -->
        <section class="mx-auto max-w-6xl px-4 pb-20 sm:px-6">
            <Card :padded="false" class="overflow-hidden">
                <div class="flex flex-col items-center gap-6 bg-md-primary px-6 py-12 text-center sm:px-12">
                    <h2 class="max-w-2xl text-2xl font-semibold text-md-on-primary sm:text-3xl">
                        Ready to experience the new interface?
                    </h2>
                    <Button :href="route('contact')" variant="tonal" size="lg">Talk to us</Button>
                </div>
            </Card>
        </section>
    </PublicLayout>
</template>
