<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeoHead from '@/Components/Public/SeoHead.vue';
import ProseContent from '@/Components/Public/ProseContent.vue';
import Card from '@/Components/Ui/Card.vue';
import type { Seo } from '@/types/home';
import type { BlogDetail, RecentBlog, ShareUrls } from '@/types/public';

defineProps<{ seo: Seo; blog: BlogDetail; recentBlogs: RecentBlog[]; share: ShareUrls }>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="grid gap-10 lg:grid-cols-3">
                <article class="lg:col-span-2">
                    <p class="text-sm text-md-on-surface-variant">{{ blog.date }}</p>
                    <h1 class="mt-2 text-3xl font-semibold text-md-on-background sm:text-4xl">{{ blog.title }}</h1>
                    <img
                        v-if="blog.image"
                        :src="blog.image"
                        alt=""
                        class="mt-6 w-full rounded-3xl object-cover"
                    />
                    <Card class="mt-6">
                        <ProseContent :html="blog.description" />
                    </Card>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <span class="text-sm text-md-on-surface-variant">Share:</span>
                        <a :href="share.facebook" target="_blank" rel="noopener" class="rounded-full bg-md-surface-container px-4 py-2 text-sm text-md-on-surface transition hover:text-md-primary">Facebook</a>
                        <a :href="share.linkedin" target="_blank" rel="noopener" class="rounded-full bg-md-surface-container px-4 py-2 text-sm text-md-on-surface transition hover:text-md-primary">LinkedIn</a>
                        <a :href="share.twitter" target="_blank" rel="noopener" class="rounded-full bg-md-surface-container px-4 py-2 text-sm text-md-on-surface transition hover:text-md-primary">Twitter</a>
                    </div>
                </article>

                <aside>
                    <Card>
                        <h2 class="text-lg font-semibold text-md-on-surface">Recent Posts</h2>
                        <ul class="mt-4 space-y-4">
                            <li v-for="(r, i) in recentBlogs" :key="i" class="flex gap-3">
                                <img
                                    v-if="r.image"
                                    :src="r.image"
                                    alt=""
                                    class="h-14 w-14 shrink-0 rounded-lg object-cover"
                                />
                                <div>
                                    <Link :href="r.url" class="text-sm font-medium text-md-on-surface transition hover:text-md-primary">
                                        {{ r.title }}
                                    </Link>
                                    <p class="mt-1 text-xs text-md-on-surface-variant">{{ r.date }}</p>
                                </div>
                            </li>
                        </ul>
                    </Card>
                </aside>
            </div>
        </section>
    </PublicLayout>
</template>
