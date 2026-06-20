<script setup lang="ts">
import Icon from '@/Components/Ui/Icon.vue';
import type { DashboardWallet } from '@/types/user';

defineProps<{ wallet: DashboardWallet; showTransfer?: boolean }>();
</script>

<template>
    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
        <div class="flex items-center gap-2 text-md-on-surface-variant">
            <Icon name="wallet" size="1.1rem" />
            <span class="text-xs font-medium uppercase tracking-wide">{{ wallet.code }}</span>
        </div>
        <p class="mt-3 text-2xl font-semibold text-md-on-surface">
            {{ wallet.symbol }}{{ wallet.balance }}
        </p>
        <a
            v-if="showTransfer"
            :href="wallet.transferUrl || '#'"
            class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-md-primary hover:underline"
        >
            Transfer <Icon name="transfer" size="1rem" />
        </a>
    </div>
</template>
