<script setup lang="ts">
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import Card from '@/Components/Ui/Card.vue';
import type { SectionContent, SectionElement } from '@/types/home';

// `service_icon` is admin-authored HTML/SVG (same trust as the Blade partial, which
// echoes it raw); rendered via v-html intentionally.
defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="bg-md-surface-container/40 py-20">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <SectionHeading
                :eyebrow="content.subheading || undefined"
                :title="content.heading || ''"
                align="center"
            />
            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="(el, i) in elements" :key="i" interactive>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-md-primary-container text-md-on-primary-container [&_svg]:h-7 [&_svg]:w-7"
                        v-html="el.service_icon || ''"
                    ></div>
                    <h3 class="mt-5 text-lg font-semibold text-md-on-surface">{{ el.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-md-on-surface-variant">{{ el.description }}</p>
                </Card>
            </div>
        </div>
    </section>
</template>
