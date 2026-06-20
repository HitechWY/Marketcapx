<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';

// NATIVE HTML POST to the existing submitProfile() (UNCHANGED backend): same action,
// method, field names (firstname/lastname/address/state/zip/city/image), _token, and
// multipart enctype. Validation errors come back via Inertia's shared `errors`.
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
const props = defineProps<{ profile: Profile; actionUrl: string }>();

const page = usePage();
const errors = computed(() => ((page.props as Record<string, any>).errors ?? {}) as Record<string, string>);
function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-md-on-surface placeholder:text-md-on-surface-variant/70 focus:border-md-primary focus:outline-none focus:ring-1 focus:ring-md-primary';
</script>

<template>
    <form :action="actionUrl" method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="_token" :value="csrf()" />

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">First name <span class="text-md-error">*</span></label>
                <input name="firstname" type="text" :value="profile.firstname ?? ''" required :class="inputClass" />
                <p v-if="errors.firstname" class="mt-1 text-xs text-md-error">{{ errors.firstname }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Last name <span class="text-md-error">*</span></label>
                <input name="lastname" type="text" :value="profile.lastname ?? ''" required :class="inputClass" />
                <p v-if="errors.lastname" class="mt-1 text-xs text-md-error">{{ errors.lastname }}</p>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Address</label>
            <input name="address" type="text" :value="profile.address ?? ''" :class="inputClass" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">State</label>
                <input name="state" type="text" :value="profile.state ?? ''" :class="inputClass" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Zip</label>
                <input name="zip" type="text" :value="profile.zip ?? ''" :class="inputClass" />
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-medium text-md-on-surface">City</label>
                <input name="city" type="text" :value="profile.city ?? ''" :class="inputClass" />
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Profile image</label>
            <input name="image" type="file" accept="image/png,image/jpeg" :class="inputClass" />
            <p v-if="errors.image" class="mt-1 text-xs text-md-error">{{ errors.image }}</p>
        </div>

        <Button type="submit">Save changes</Button>
    </form>
</template>
