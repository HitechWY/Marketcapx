<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Card from '@/Components/Ui/Card.vue';
import type { BlogListItem } from '@/types/public';

defineProps<{ blog: BlogListItem }>();
</script>

<template>
    <Card :padded="false" interactive>
        <Link :href="blog.url" class="block overflow-hidden rounded-3xl">
            <div class="relative">
                <img v-if="blog.image" :src="blog.image" alt="" class="h-48 w-full object-cover" />
                <div class="absolute left-4 top-4 rounded-xl bg-md-primary px-3 py-1.5 text-center text-md-on-primary">
                    <div class="text-lg font-bold leading-none">{{ blog.day }}</div>
                    <div class="text-[10px] font-medium uppercase tracking-wide">{{ blog.month }}</div>
                </div>
            </div>
            <div class="p-6">
                <h3 class="text-lg font-semibold text-md-on-surface">{{ blog.title }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-md-on-surface-variant">{{ blog.excerpt }}</p>
                <span class="mt-4 inline-block text-sm font-medium text-md-primary">Read more →</span>
            </div>
        </Link>
    </Card>
</template>
