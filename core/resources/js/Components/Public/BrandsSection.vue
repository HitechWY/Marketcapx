<script setup lang="ts">
import type { SectionContent, SectionElement } from '@/types/home';

defineProps<{ content: SectionContent; elements: SectionElement[] }>();
</script>

<template>
    <section class="border-y border-md-outline-variant/40 bg-md-surface-container/40">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <p v-if="content.heading" class="text-center text-sm font-medium text-md-on-surface-variant">
                {{ content.heading }}
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-6 opacity-80">
                <img
                    v-for="(el, i) in elements"
                    :key="i"
                    :src="el.image || ''"
                    alt="brand"
                    class="h-7 w-auto grayscale transition hover:grayscale-0"
                />
            </div>
        </div>
    </section>
</template>
