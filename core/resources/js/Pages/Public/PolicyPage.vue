<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeoHead from '@/Components/Public/SeoHead.vue';
import ProseContent from '@/Components/Public/ProseContent.vue';
import Card from '@/Components/Ui/Card.vue';
import type { Seo } from '@/types/home';

defineProps<{ seo: Seo; title: string; details: string | null }>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
            <h1 class="text-3xl font-semibold text-md-on-background sm:text-4xl">{{ title }}</h1>
            <Card class="mt-8">
                <ProseContent :html="details" />
            </Card>
        </section>
    </PublicLayout>
</template>
