<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeoHead from '@/Components/Public/SeoHead.vue';
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import BlogCard from '@/Components/Public/BlogCard.vue';
import Pagination from '@/Components/Public/Pagination.vue';
import type { Seo } from '@/types/home';
import type { BlogListItem, Paginator } from '@/types/public';

defineProps<{
    seo: Seo;
    content: { title: string | null; heading: string | null; subheading: string | null };
    blogs: Paginator<BlogListItem>;
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <SectionHeading
                :eyebrow="content.title || undefined"
                :title="content.heading || 'Announcements'"
                :subtitle="content.subheading || undefined"
                align="center"
            />

            <div v-if="blogs.data.length" class="mt-12 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <BlogCard v-for="(blog, i) in blogs.data" :key="i" :blog="blog" />
            </div>
            <p v-else class="mt-12 text-center text-md-on-surface-variant">No announcements yet.</p>

            <div class="mt-12">
                <Pagination :links="blogs.links" />
            </div>
        </section>
    </PublicLayout>
</template>
