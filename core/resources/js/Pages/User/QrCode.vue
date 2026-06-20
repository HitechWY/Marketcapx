<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';

defineProps<{ qrCode: string; uniqueCode: string; downloadUrl: string }>();
</script>

<template>
    <Head title="My QR Code" />

    <UserLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">My QR Code</h1>
            <Card class="mx-auto max-w-md text-center">
                <img :src="qrCode" alt="QR code" class="mx-auto w-1/2 rounded-xl bg-white p-3" />
                <p class="mt-4 text-sm text-md-on-surface-variant">{{ uniqueCode }}</p>
                <Button :href="downloadUrl" class="mt-6">Download as JPG</Button>
            </Card>
        </div>
    </UserLayout>
</template>
