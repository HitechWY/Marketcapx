<script setup lang="ts">
import type { SectionContent, SectionElement } from '@/types/home';

// Maps the CMS "overview" section (counter_digit + title) to a stats band.
defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="bg-md-primary py-16 text-md-on-primary">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div v-if="content.heading" class="mb-10 text-center">
                <h2 class="text-2xl font-semibold sm:text-3xl">{{ content.heading }}</h2>
                <p v-if="content.subheading" class="mt-2 text-md-on-primary/80">{{ content.subheading }}</p>
            </div>
            <div class="grid grid-cols-2 gap-8 text-center sm:grid-cols-4">
                <div v-for="(el, i) in elements" :key="i">
                    <div class="text-4xl font-bold sm:text-5xl">{{ el.counter_digit }}</div>
                    <div class="mt-2 text-sm text-md-on-primary/80">{{ el.title }}</div>
                </div>
            </div>
        </div>
    </section>
</template>
