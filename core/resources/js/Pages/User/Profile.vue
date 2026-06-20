<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import ProfileForm from '@/Components/User/ProfileForm.vue';

interface Profile {
    fullname: string;
    username: string;
    email: string;
    mobile: string | null;
    country: string | null;
    image: string | null;
    firstname: string | null;
    lastname: string | null;
    address: string | null;
    state: string | null;
    zip: string | null;
    city: string | null;
}
defineProps<{ profile: Profile; actionUrl: string }>();
</script>

<template>
    <Head title="Profile Setting" />

    <UserLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Profile</h1>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-1">
                    <div class="flex flex-col items-center text-center">
                        <img
                            v-if="profile.image"
                            :src="profile.image"
                            alt=""
                            class="h-20 w-20 rounded-full object-cover ring-1 ring-md-outline-variant/50"
                        />
                        <h2 class="mt-3 text-lg font-semibold text-md-on-surface">{{ profile.fullname }}</h2>
                    </div>
                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-md-on-surface-variant">Username</dt>
                            <dd class="font-medium text-md-on-surface">{{ profile.username }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-md-on-surface-variant">Email</dt>
                            <dd class="font-medium text-md-on-surface">{{ profile.email }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-md-on-surface-variant">Mobile</dt>
                            <dd class="font-medium text-md-on-surface">{{ profile.mobile }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-md-on-surface-variant">Country</dt>
                            <dd class="font-medium text-md-on-surface">{{ profile.country }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card class="lg:col-span-2">
                    <ProfileForm :profile="profile" :action-url="actionUrl" />
                </Card>
            </div>
        </div>
    </UserLayout>
</template>
