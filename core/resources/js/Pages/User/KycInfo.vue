<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card.vue';

interface KycField {
    name: string;
    type: string;
    value: string;
}
defineProps<{ fields: KycField[] }>();
</script>

<template>
    <Head title="KYC Data" />

    <UserLayout>
        <div class="mx-auto max-w-2xl space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">KYC Documents</h1>

            <Card :padded="false">
                <ul v-if="fields.length" class="divide-y divide-md-outline-variant/40">
                    <li v-for="(f, i) in fields" :key="i" class="flex items-center justify-between gap-4 px-5 py-3.5">
                        <span class="text-sm font-semibold text-md-on-surface">{{ f.name }}</span>
                        <span class="text-right text-sm text-md-on-surface-variant">
                            <a v-if="f.type === 'file'" :href="f.value" class="text-md-primary hover:underline">Attachment</a>
                            <span v-else>{{ f.value }}</span>
                        </span>
                    </li>
                </ul>
                <p v-else class="p-8 text-center text-md-on-surface-variant">KYC data not found.</p>
            </Card>
        </div>
    </UserLayout>
</template>
