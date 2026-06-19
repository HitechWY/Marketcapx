<script setup lang="ts">
import type { Component } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import HeroSection from '@/Components/Public/HeroSection.vue';
import BrandsSection from '@/Components/Public/BrandsSection.vue';
import AboutSection from '@/Components/Public/AboutSection.vue';
import ServiceSection from '@/Components/Public/ServiceSection.vue';
import WhyChooseUsSection from '@/Components/Public/WhyChooseUsSection.vue';
import FeatureSection from '@/Components/Public/FeatureSection.vue';
import BusinessSection from '@/Components/Public/BusinessSection.vue';
import StatsSection from '@/Components/Public/StatsSection.vue';
import TestimonialSection from '@/Components/Public/TestimonialSection.vue';
import SubscribeSection from '@/Components/Public/SubscribeSection.vue';
import BlogSection from '@/Components/Public/BlogSection.vue';
import type { HomeData, Seo } from '@/types/home';

defineProps<{ seo: Seo; home: HomeData }>();

// Map CMS section keys -> Vue components, mirroring the Blade sections/* partials.
const sectionComponents: Record<string, Component> = {
    brands: BrandsSection,
    about: AboutSection,
    service: ServiceSection,
    why_choose_us: WhyChooseUsSection,
    feature: FeatureSection,
    business: BusinessSection,
    overview: StatsSection,
    testimonial: TestimonialSection,
    subscribe: SubscribeSection,
    blog: BlogSection,
};
</script>

<template>
    <Head :title="seo.title">
        <meta v-if="seo.description" name="description" :content="seo.description" head-key="description" />
        <meta v-if="seo.keywords" name="keywords" :content="seo.keywords" head-key="keywords" />
        <meta property="og:title" :content="seo.title" head-key="og:title" />
        <meta v-if="seo.description" property="og:description" :content="seo.description" head-key="og:description" />
        <meta v-if="seo.image" property="og:image" :content="seo.image" head-key="og:image" />
    </Head>

    <PublicLayout>
        <HeroSection v-if="home.banner" :banner="home.banner" />

        <template v-for="section in home.sections" :key="section.key">
            <component
                :is="sectionComponents[section.key]"
                v-if="sectionComponents[section.key]"
                :content="section.content"
                :elements="section.elements"
            />
        </template>
    </PublicLayout>
</template>
