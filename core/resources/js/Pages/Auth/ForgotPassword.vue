<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/Ui/Button.vue';

// Guard-agnostic: user / merchant / agent all render this with their own URLs.
// NATIVE HTML POST to the existing sendResetCodeEmail() (UNCHANGED backend): field `value`.
defineProps<{ submitUrl: string; loginUrl: string }>();

const page = usePage();
const errors = computed(() => ((page.props as Record<string, any>).errors ?? {}) as Record<string, string>);
function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none focus:ring-1 focus:ring-md-primary';
</script>

<template>
    <Head title="Account Recovery" />

    <AuthLayout>
        <h1 class="text-xl font-semibold text-md-on-surface">Account Recovery</h1>
        <p class="mt-1 mb-6 text-sm text-md-on-surface-variant">Enter your username or email and we'll send a reset code.</p>

        <form :action="submitUrl" method="post" class="space-y-4">
            <input type="hidden" name="_token" :value="csrf()" />

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Username or Email <span class="text-md-error">*</span></label>
                <input name="value" type="text" required autocomplete="username" :class="inputClass" />
                <p v-if="errors.value" class="mt-1 text-xs text-md-error">{{ errors.value }}</p>
            </div>

            <Button type="submit" :block="true">Send reset code</Button>
        </form>

        <p class="mt-6 text-center text-sm text-md-on-surface-variant">
            <a :href="loginUrl" class="font-medium text-md-primary hover:underline">Back to login</a>
        </p>
    </AuthLayout>
</template>
