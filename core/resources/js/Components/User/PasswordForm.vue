<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';

// NATIVE HTML POST to the existing submitPassword() (UNCHANGED backend): same action,
// method, field names (current_password/password/password_confirmation), _token.
const props = defineProps<{ actionUrl: string; securePassword: boolean }>();

const page = usePage();
const errors = computed(() => ((page.props as Record<string, any>).errors ?? {}) as Record<string, string>);
function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none focus:ring-1 focus:ring-md-primary';
</script>

<template>
    <form :action="actionUrl" method="post" class="max-w-md space-y-4">
        <input type="hidden" name="_token" :value="csrf()" />

        <div>
            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Current password <span class="text-md-error">*</span></label>
            <input name="current_password" type="password" required autocomplete="current-password" :class="inputClass" />
            <p v-if="errors.current_password" class="mt-1 text-xs text-md-error">{{ errors.current_password }}</p>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">New password <span class="text-md-error">*</span></label>
            <input name="password" type="password" required autocomplete="new-password" :class="inputClass" />
            <p v-if="securePassword" class="mt-1 text-xs text-md-on-surface-variant">
                Use upper & lower case, a number, and a symbol (min 6 characters).
            </p>
            <p v-if="errors.password" class="mt-1 text-xs text-md-error">{{ errors.password }}</p>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Confirm password <span class="text-md-error">*</span></label>
            <input name="password_confirmation" type="password" required autocomplete="new-password" :class="inputClass" />
        </div>

        <Button type="submit">Change password</Button>
    </form>
</template>
