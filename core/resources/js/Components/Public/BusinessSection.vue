<script setup lang="ts">
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
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
        <div class="mt-12 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-6">
            <div
                v-for="(el, i) in elements"
                :key="i"
                class="flex items-center justify-center rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40"
            >
                <img :src="el.client_logo || ''" alt="client" class="h-9 w-auto opacity-80" />
            </div>
        </div>
    </section>
</template>
