<script setup lang="ts">
import type { Component } from 'vue';
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
import type { HomeSection } from '@/types/home';

// Renders an ordered list of CMS sections by key, mirroring the Blade
// sections/* partials. Shared by Home, generic Page, and Contact.
defineProps<{ sections: HomeSection[] }>();

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
    <template v-for="section in sections" :key="section.key">
        <component
            :is="sectionComponents[section.key]"
            v-if="sectionComponents[section.key]"
            :content="section.content"
            :elements="section.elements"
        />
    </template>
</template>
