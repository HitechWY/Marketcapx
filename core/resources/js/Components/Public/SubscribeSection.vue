<script setup lang="ts">
import { ref } from 'vue';
import { route } from 'ziggy-js';
import Button from '@/Components/Ui/Button.vue';
import type { SectionContent } from '@/types/home';

defineProps<{ content: SectionContent; elements?: unknown[] }>();

// Newsletter subscribe — preserves the EXISTING backend contract exactly:
//   POST route('subscribe')  body: email (+ CSRF)
//   response JSON: { success:true, message } | { error: string[] }
// (Same URL, method, field name, CSRF, and response handling as the original
// jQuery AJAX in sections/subscribe.blade.php. No backend route/logic changed.)
const email = ref('');
const status = ref<{ type: 'success' | 'error'; text: string } | null>(null);
const submitting = ref(false);

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

async function submit(): Promise<void> {
    status.value = null;
    if (!email.value) {
        status.value = { type: 'error', text: 'Email field is required' };
        return;
    }
    submitting.value = true;
    try {
        const res = await fetch(route('subscribe'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ email: email.value }),
        });
        const data = await res.json();
        if (data.success) {
            status.value = { type: 'success', text: data.message };
            email.value = '';
        } else if (data.error) {
            status.value = { type: 'error', text: (data.error as string[]).join(' ') };
        }
    } catch {
        status.value = { type: 'error', text: 'Something went wrong. Please try again.' };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div class="overflow-hidden rounded-3xl bg-md-primary px-6 py-12 text-md-on-primary sm:px-12">
            <div class="grid items-center gap-8 lg:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-semibold sm:text-3xl">{{ content.heading }}</h2>
                    <p v-if="content.subheading" class="mt-3 text-md-on-primary/80">{{ content.subheading }}</p>
                </div>

                <form class="flex w-full flex-col gap-3 sm:flex-row" @submit.prevent="submit">
                    <input
                        v-model="email"
                        type="email"
                        name="email"
                        placeholder="Enter your email"
                        class="h-12 w-full rounded-full border-0 bg-md-surface px-5 text-md-on-surface placeholder:text-md-on-surface-variant focus:outline-none focus:ring-2 focus:ring-md-on-primary"
                    />
                    <Button type="submit" variant="tonal" size="lg" :block="false">
                        {{ submitting ? 'Sending…' : 'Subscribe' }}
                    </Button>
                </form>
            </div>

            <p
                v-if="status"
                class="mt-4 text-sm"
                :class="status.type === 'success' ? 'text-md-on-primary' : 'text-md-on-primary/90 font-medium'"
            >
                {{ status.text }}
            </p>
        </div>
    </section>
</template>
