<script setup lang="ts">
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import Button from '@/Components/Ui/Button.vue';
import type { SectionContent, SectionElement } from '@/types/home';

defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div>
                <SectionHeading
                    :eyebrow="content.title || undefined"
                    :title="content.heading || ''"
                    :subtitle="content.short_details || undefined"
                />
                <Button
                    v-if="content.button_name"
                    :href="content.button_link || '#'"
                    class="mt-8"
                >
                    {{ content.button_name }}
                </Button>
            </div>

            <div class="rounded-3xl bg-md-surface-container p-8 ring-1 ring-md-outline-variant/40">
                <p v-if="content.experience_text" class="text-lg font-medium text-md-on-surface">
                    {{ content.experience_text }}
                </p>
                <div class="mt-6 flex flex-wrap items-center gap-6">
                    <img
                        v-for="(el, i) in elements"
                        :key="i"
                        :src="el.award_logo || ''"
                        alt="award"
                        class="h-16 w-16 rounded-2xl bg-md-surface object-contain p-2 ring-1 ring-md-outline-variant/40"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
