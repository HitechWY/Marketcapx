<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeoHead from '@/Components/Public/SeoHead.vue';
import SectionRenderer from '@/Components/Public/SectionRenderer.vue';
import type { Seo, HomeSection } from '@/types/home';

// Generic CMS page (the {slug} catch-all). Renders the page's `secs` sections,
// reusing the same section components as the homepage (no banner).
defineProps<{ seo: Seo; sections: HomeSection[] }>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <SectionRenderer :sections="sections" />
    </PublicLayout>
</template>
