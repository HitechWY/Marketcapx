<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SeoHead from '@/Components/Public/SeoHead.vue';
import SectionRenderer from '@/Components/Public/SectionRenderer.vue';
import SectionHeading from '@/Components/Ui/SectionHeading.vue';
import ContactForm from '@/Components/Public/ContactForm.vue';
import type { Seo, HomeSection } from '@/types/home';

defineProps<{
    seo: Seo;
    content: {
        title: string | null;
        heading: string | null;
        address: string | null;
        email_address: string | null;
        contact_number: string | null;
    };
    authUser: { name: string; email: string } | null;
    captcha: string;
    sections: HomeSection[];
}>();
</script>

<template>
    <SeoHead :seo="seo" />

    <PublicLayout>
        <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
            <div class="grid gap-10 lg:grid-cols-2">
                <!-- Contact info -->
                <div>
                    <SectionHeading
                        :eyebrow="content.title || undefined"
                        :title="content.heading || 'Get in touch'"
                    />
                    <div class="mt-8 space-y-4">
                        <div v-if="content.address" class="flex items-start gap-4 rounded-2xl bg-md-surface-container p-4">
                            <span class="text-xl">📍</span>
                            <div>
                                <h4 class="font-semibold text-md-on-surface">Our Address</h4>
                                <p class="mt-1 text-sm text-md-on-surface-variant">{{ content.address }}</p>
                            </div>
                        </div>
                        <div v-if="content.email_address" class="flex items-start gap-4 rounded-2xl bg-md-surface-container p-4">
                            <span class="text-xl">✉️</span>
                            <div>
                                <h4 class="font-semibold text-md-on-surface">Email Address</h4>
                                <a :href="`mailto:${content.email_address}`" class="mt-1 block text-sm text-md-primary hover:underline">{{ content.email_address }}</a>
                            </div>
                        </div>
                        <div v-if="content.contact_number" class="flex items-start gap-4 rounded-2xl bg-md-surface-container p-4">
                            <span class="text-xl">📞</span>
                            <div>
                                <h4 class="font-semibold text-md-on-surface">Phone Number</h4>
                                <a :href="`tel:${content.contact_number}`" class="mt-1 block text-sm text-md-primary hover:underline">{{ content.contact_number }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact form -->
                <div>
                    <ContactForm :auth-user="authUser" :captcha="captcha" />
                </div>
            </div>
        </section>

        <SectionRenderer :sections="sections" />
    </PublicLayout>
</template>
