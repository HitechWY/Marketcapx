<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeoHead from '@/Components/Public/SeoHead.vue';
import HeroSection from '@/Components/Public/HeroSection.vue';
import SectionRenderer from '@/Components/Public/SectionRenderer.vue';
import type { HomeData, Seo } from '@/types/home';

defineProps<{ seo: Seo; home: HomeData }>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <HeroSection v-if="home.banner" :banner="home.banner" />
        <SectionRenderer :sections="home.sections" />
    </PublicLayout>
</template>
