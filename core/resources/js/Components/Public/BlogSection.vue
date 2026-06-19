<script setup lang="ts">
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import Card from '@/Components/Ui/Card.vue';
import type { SectionContent, SectionElement } from '@/types/home';

defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="bg-md-surface-container/40 py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <SectionHeading
                :eyebrow="content.title || undefined"
                :title="content.heading || ''"
                :subtitle="content.subheading || undefined"
                align="center"
            />
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <Card v-for="(el, i) in elements" :key="i" :padded="false" interactive>
                    <a :href="el.url || '#'" class="block overflow-hidden rounded-3xl">
                        <img
                            v-if="el.image"
                            :src="el.image"
                            alt=""
                            class="h-44 w-full object-cover"
                        />
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-md-on-surface">{{ el.title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-md-on-surface-variant">{{ el.excerpt }}</p>
                            <span class="mt-4 inline-block text-sm font-medium text-md-primary">Read more →</span>
                        </div>
                    </a>
                </Card>
            </div>
        </div>
    </section>
</template>
