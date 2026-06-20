<script setup lang="ts">
import { computed, ref } from 'vue';
import type { DashboardKyc } from '@/types/user';

// Mirrors the Blade dashboard KYC alerts: rejected (unverified + reason), unverified,
// or pending. Renders nothing when verified (status 1) or no actionable state.
const props = defineProps<{ kyc: DashboardKyc }>();

const showReason = ref(false);

const state = computed<'rejected' | 'unverified' | 'pending' | null>(() => {
    if (props.kyc.status === 0 && props.kyc.rejectionReason) return 'rejected';
    if (props.kyc.status === 0) return 'unverified';
    if (props.kyc.status === 2) return 'pending';
    return null;
});

const tone = computed(() => {
    switch (state.value) {
        case 'rejected':
            return 'border-md-error/40 bg-md-error/10';
        case 'pending':
            return 'border-amber-500/40 bg-amber-500/10';
        default:
            return 'border-md-primary/40 bg-md-primary/10';
    }
});

const title = computed(() => {
    switch (state.value) {
        case 'rejected':
            return 'KYC Documents Rejected';
        case 'pending':
            return 'KYC Verification Pending';
        default:
            return 'KYC Verification Required';
    }
});

const message = computed(() => {
    switch (state.value) {
        case 'rejected':
            return props.kyc.messages.reject;
        case 'pending':
            return props.kyc.messages.pending;
        default:
            return props.kyc.messages.required;
    }
});
</script>

<template>
    <div v-if="state" class="rounded-2xl border p-5" :class="tone">
        <h3 class="font-semibold text-md-on-surface">{{ title }}</h3>
        <p class="mt-2 text-sm text-md-on-surface-variant">{{ message }}</p>

        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
            <a v-if="state !== 'pending'" :href="kyc.formUrl" class="font-medium text-md-primary hover:underline">
                {{ state === 'rejected' ? 'Re-submit documents' : 'Submit KYC' }}
            </a>
            <a :href="kyc.dataUrl" class="font-medium text-md-primary hover:underline">See KYC data</a>
            <button
                v-if="state === 'rejected'"
                type="button"
                class="font-medium text-md-error hover:underline"
                @click="showReason = !showReason"
            >
                {{ showReason ? 'Hide reason' : 'Show reason' }}
            </button>
        </div>

        <p v-if="showReason && kyc.rejectionReason" class="mt-3 rounded-lg bg-md-surface p-3 text-sm text-md-on-surface-variant">
            {{ kyc.rejectionReason }}
        </p>
    </div>
</template>
