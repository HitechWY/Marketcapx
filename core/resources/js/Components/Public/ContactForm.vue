<script setup lang="ts">
import { route } from 'ziggy-js';
import Button from '@/Components/Ui/Button.vue';

// NATIVE HTML form POST to the existing POST /contact (SiteController@contactSubmit) —
// unchanged backend. Full-page submit → controller validates, runs verifyCaptcha(),
// creates the support ticket, and redirects to ticket.view with flash. The contract is
// byte-identical to the Blade form: same action, method, field names, _token, and the
// server-rendered captcha markup (when enabled). NOT an Inertia/fetch form.
const props = defineProps<{
    authUser: { name: string; email: string } | null;
    captcha: string;
}>();

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none focus:ring-1 focus:ring-md-primary read-only:opacity-70';
</script>

<template>
    <form
        method="POST"
        :action="route('contact')"
        class="rounded-3xl bg-md-surface p-6 ring-1 ring-md-outline-variant/40 sm:p-8"
    >
        <input type="hidden" name="_token" :value="csrfToken()" />

        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">
                    Name <span class="text-md-error">*</span>
                </label>
                <input
                    name="name"
                    type="text"
                    :value="props.authUser?.name ?? ''"
                    :readonly="!!props.authUser"
                    :required="!props.authUser"
                    placeholder="Enter your name"
                    :class="inputClass"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">
                    Email <span class="text-md-error">*</span>
                </label>
                <input
                    name="email"
                    type="text"
                    :value="props.authUser?.email ?? ''"
                    :readonly="!!props.authUser"
                    :required="!props.authUser"
                    placeholder="Enter your email address"
                    :class="inputClass"
                />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">
                    Subject <span class="text-md-error">*</span>
                </label>
                <input name="subject" type="text" required placeholder="Enter your subject" :class="inputClass" />
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">
                    Message <span class="text-md-error">*</span>
                </label>
                <textarea name="message" rows="5" required placeholder="Enter your message" :class="inputClass"></textarea>
            </div>

            <!-- Server-rendered captcha (custom image / reCAPTCHA) when enabled; empty otherwise. -->
            <div v-if="props.captcha" v-html="props.captcha"></div>

            <Button type="submit" :block="true" size="lg">Submit</Button>
        </div>
    </form>
</template>
