<script setup lang="ts">
import Button from '@/Components/Ui/Button.vue';
import type { Banner } from '@/types/home';

defineProps<{ banner: Banner }>();
</script>

<template>
    <section class="relative overflow-hidden">
        <!-- Background image (CMS) with a token-based gradient veil for contrast -->
        <div class="absolute inset-0 -z-10">
            <img
                v-if="banner.background_image"
                :src="banner.background_image"
                alt=""
                class="h-full w-full object-cover opacity-20"
            />
            <div class="absolute inset-0 bg-gradient-to-b from-md-primary/10 via-md-background/60 to-md-background"></div>
        </div>

        <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
            <div class="mx-auto max-w-3xl text-center">
                <p
                    v-if="banner.title"
                    class="inline-flex items-center rounded-full bg-md-primary-container px-4 py-1 text-xs font-semibold uppercase tracking-widest text-md-on-primary-container"
                >
                    {{ banner.title }}
                </p>

                <h1 class="mt-6 text-4xl font-bold leading-tight text-md-on-background sm:text-6xl">
                    {{ banner.heading }}
                </h1>

                <p v-if="banner.subheading" class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-md-on-surface-variant">
                    {{ banner.subheading }}
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Button v-if="banner.button_name" :href="banner.button_link || '#'" size="lg">
                        {{ banner.button_name }}
                    </Button>
                    <a
                        v-if="banner.video_button_name && banner.video_link"
                        :href="banner.video_link"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-2 text-sm font-medium text-md-on-surface transition hover:text-md-primary"
                    >
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-md-surface-container ring-1 ring-md-outline-variant/50">▶</span>
                        {{ banner.video_button_name }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>
