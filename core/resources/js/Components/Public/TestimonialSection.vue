<script setup lang="ts">
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import Card from '@/Components/Ui/Card.vue';
import type { SectionContent, SectionElement } from '@/types/home';

defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <SectionHeading
            :eyebrow="content.title || undefined"
            :title="content.heading || ''"
            :subtitle="content.subheading || undefined"
            align="center"
        />
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            <Card v-for="(el, i) in elements" :key="i">
                <p class="text-sm leading-relaxed text-md-on-surface-variant">“{{ el.quote }}”</p>
                <div class="mt-6 flex items-center gap-3">
                    <img
                        v-if="el.author_image"
                        :src="el.author_image"
                        alt=""
                        class="h-11 w-11 rounded-full object-cover ring-1 ring-md-outline-variant/50"
                    />
                    <div>
                        <div class="text-sm font-semibold text-md-on-surface">{{ el.author_name }}</div>
                        <div class="text-xs text-md-on-surface-variant">{{ el.designation }}</div>
                    </div>
                </div>
            </Card>
        </div>
    </section>
</template>
