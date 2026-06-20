<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/Components/Ui/Button.vue';

// Guard-agnostic. NATIVE HTML POST to the existing verifyCode() (UNCHANGED backend):
// hidden `email` + `code` (server strips spaces from code).
defineProps<{ submitUrl: string; email: string; requestUrl: string }>();

const page = usePage();
const errors = computed(() => ((page.props as Record<string, any>).errors ?? {}) as Record<string, string>);
function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-center text-lg tracking-[0.5em] text-md-on-surface focus:border-md-primary focus:outline-none focus:ring-1 focus:ring-md-primary';
</script>

<template>
    <Head title="Verify Email" />

    <AuthLayout>
        <h1 class="text-xl font-semibold text-md-on-surface">Verify your email</h1>
        <p class="mt-1 mb-6 text-sm text-md-on-surface-variant">Enter the 6-digit code sent to your email.</p>

        <form :action="submitUrl" method="post" class="space-y-4">
            <input type="hidden" name="_token" :value="csrf()" />
            <input type="hidden" name="email" :value="email" />

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Verification Code <span class="text-md-error">*</span></label>
                <input name="code" type="text" inputmode="numeric" maxlength="7" required autocomplete="one-time-code" :class="inputClass" />
                <p v-if="errors.code" class="mt-1 text-xs text-md-error">{{ errors.code }}</p>
            </div>

            <Button type="submit" :block="true">Verify code</Button>
        </form>

        <p class="mt-6 text-center text-sm text-md-on-surface-variant">
            <a :href="requestUrl" class="font-medium text-md-primary hover:underline">Request a new code</a>
        </p>
    </AuthLayout>
</template>
