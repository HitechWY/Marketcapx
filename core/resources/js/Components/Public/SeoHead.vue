<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { Seo } from '@/types/home';

// Shared SEO head for public Inertia pages: title (+ "— MarketCap X" via the global
// title callback) plus description/keywords and Open Graph tags. head-key dedupes
// tags across Inertia navigations.
defineProps<{ seo: Seo }>();
</script>

<template>
    <Head :title="seo.title">
        <meta v-if="seo.description" name="description" :content="seo.description" head-key="description" />
        <meta v-if="seo.keywords" name="keywords" :content="seo.keywords" head-key="keywords" />
        <meta property="og:title" :content="seo.title" head-key="og:title" />
        <meta v-if="seo.description" property="og:description" :content="seo.description" head-key="og:description" />
        <meta v-if="seo.image" property="og:image" :content="seo.image" head-key="og:image" />
    </Head>
</template>
