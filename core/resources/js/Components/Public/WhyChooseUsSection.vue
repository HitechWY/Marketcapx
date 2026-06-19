<script setup lang="ts">
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import type { SectionContent, SectionElement } from '@/types/home';

defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <SectionHeading
            :eyebrow="content.subheading || undefined"
            :title="content.heading || ''"
            align="center"
        />
        <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="(el, i) in elements" :key="i" class="flex gap-4">
                <img
                    v-if="el.icon"
                    :src="el.icon"
                    alt=""
                    class="h-12 w-12 shrink-0 rounded-xl bg-md-primary-container/60 object-contain p-2"
                />
                <div>
                    <h3 class="text-base font-semibold text-md-on-surface">{{ el.title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-md-on-surface-variant">{{ el.short_details }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
