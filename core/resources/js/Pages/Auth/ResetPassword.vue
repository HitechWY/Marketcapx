<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/Ui/Button.vue';

// Guard-agnostic. NATIVE HTML POST to the existing reset() (UNCHANGED backend):
// hidden `email` + `token`, plus `password` / `password_confirmation`.
defineProps<{ submitUrl: string; email: string; token: string; loginUrl: string; securePassword?: boolean }>();

const page = usePage();
const errors = computed(() => ((page.props as Record<string, any>).errors ?? {}) as Record<string, string>);
function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none focus:ring-1 focus:ring-md-primary';
</script>

<template>
    <Head title="Reset Password" />

    <AuthLayout>
        <h1 class="text-xl font-semibold text-md-on-surface">Reset Password</h1>
        <p class="mt-1 mb-6 text-sm text-md-on-surface-variant">Choose a new password for your account.</p>

        <form :action="submitUrl" method="post" class="space-y-4">
            <input type="hidden" name="_token" :value="csrf()" />
            <input type="hidden" name="email" :value="email" />
            <input type="hidden" name="token" :value="token" />

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">New password <span class="text-md-error">*</span></label>
                <input name="password" type="password" required autocomplete="new-password" :class="inputClass" />
                <p v-if="securePassword" class="mt-1 text-xs text-md-on-surface-variant">
                    Use upper &amp; lower case, a number, and a symbol (min 6 characters).
                </p>
                <p v-if="errors.password" class="mt-1 text-xs text-md-error">{{ errors.password }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Confirm password <span class="text-md-error">*</span></label>
                <input name="password_confirmation" type="password" required autocomplete="new-password" :class="inputClass" />
            </div>

            <Button type="submit" :block="true">Change password</Button>
        </form>

        <p class="mt-6 text-center text-sm text-md-on-surface-variant">
            <a :href="loginUrl" class="font-medium text-md-primary hover:underline">Back to login</a>
        </p>
    </AuthLayout>
</template>
