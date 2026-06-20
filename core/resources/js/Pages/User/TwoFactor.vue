<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '@/Layouts/UserLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import SecurityStatusCard from '@/Components/User/SecurityStatusCard.vue';

// 2FA enable/disable are NATIVE HTML POSTs to the UNCHANGED create2fa()/disable2fa()
// (fields: key [hidden secret] + code, + _token). verifyG2fa logic untouched.
const props = defineProps<{
    enabled: boolean;
    secret: string;
    qrCodeUrl: string;
    enableUrl: string;
    disableUrl: string;
}>();

const page = usePage();
const errors = computed(() => ((page.props as Record<string, any>).errors ?? {}) as Record<string, string>);
function csrf(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}
const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-4 py-2.5 text-md-on-surface focus:border-md-primary focus:outline-none';
</script>

<template>
    <Head title="2FA Security" />

    <UserLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold text-md-on-background">Two-Factor Security</h1>
            <SecurityStatusCard :enabled="enabled" />

            <!-- Enable -->
            <Card v-if="!enabled">
                <h2 class="text-lg font-semibold text-md-on-surface">Enable 2FA</h2>
                <p class="mt-1 text-sm text-md-on-surface-variant">
                    Scan the QR code or use the setup key in your authenticator app, then enter the code.
                </p>
                <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:items-start">
                    <img :src="qrCodeUrl" alt="2FA QR" class="h-40 w-40 rounded-xl bg-white p-2" />
                    <form :action="enableUrl" method="post" class="flex-1 space-y-3">
                        <input type="hidden" name="_token" :value="csrf()" />
                        <input type="hidden" name="key" :value="secret" />
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Setup key</label>
                            <input type="text" :value="secret" readonly :class="inputClass + ' opacity-70'" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Authenticator code</label>
                            <input name="code" type="text" required placeholder="Enter the code" :class="inputClass" />
                            <p v-if="errors.code" class="mt-1 text-xs text-md-error">{{ errors.code }}</p>
                        </div>
                        <Button type="submit">Enable 2FA</Button>
                    </form>
                </div>
            </Card>

            <!-- Disable -->
            <Card v-else>
                <h2 class="text-lg font-semibold text-md-on-surface">Disable 2FA</h2>
                <p class="mt-1 text-sm text-md-on-surface-variant">Enter a current authenticator code to disable 2FA.</p>
                <form :action="disableUrl" method="post" class="mt-4 max-w-md space-y-3">
                    <input type="hidden" name="_token" :value="csrf()" />
                    <input type="hidden" name="key" :value="secret" />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-md-on-surface">Authenticator code</label>
                        <input name="code" type="text" required placeholder="Enter the code" :class="inputClass" />
                        <p v-if="errors.code" class="mt-1 text-xs text-md-error">{{ errors.code }}</p>
                    </div>
                    <Button type="submit" variant="outlined">Disable 2FA</Button>
                </form>
            </Card>
        </div>
    </UserLayout>
</template>
