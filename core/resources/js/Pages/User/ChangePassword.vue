<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import PasswordForm from '@/Components/User/PasswordForm.vue';

defineProps<{ actionUrl: string; securePassword: boolean }>();
</script>

<template>
    <Head title="Change Password" />

    <UserLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Change Password</h1>
            <Card>
                <PasswordForm :action-url="actionUrl" :secure-password="securePassword" />
            </Card>
        </div>
    </UserLayout>
</template>
