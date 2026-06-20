<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/Components/Ui/Icon.vue';
import type { DashboardTransaction } from '@/types/user';

const props = withDefaults(
    defineProps<{ transactions: DashboardTransaction[]; emptyMessage?: string }>(),
    { emptyMessage: 'No transactions yet.' },
);

const expanded = ref<number | null>(null);
function toggle(i: number): void {
    expanded.value = expanded.value === i ? null : i;
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl bg-md-surface ring-1 ring-md-outline-variant/40">
        <div v-if="!transactions.length" class="p-8 text-center text-md-on-surface-variant">
            {{ emptyMessage }}
        </div>

        <ul v-else class="divide-y divide-md-outline-variant/40">
            <li v-for="(t, i) in transactions" :key="i">
                <button type="button" class="flex w-full items-center gap-4 px-4 py-3.5 text-left transition hover:bg-md-on-surface/5" @click="toggle(i)">
                    <span
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                        :class="t.trxType === '-' ? 'bg-md-error/10 text-md-error' : 'bg-emerald-500/10 text-emerald-500'"
                    >
                        <Icon :name="t.trxType === '-' ? 'arrow-up' : 'arrow-down'" size="1.1rem" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-md-on-surface">{{ t.remark }}</span>
                        <span class="block truncate text-xs text-md-on-surface-variant">{{ t.date }}</span>
                    </span>
                    <span class="text-right">
                        <span class="block text-sm font-semibold" :class="t.trxType === '-' ? 'text-md-error' : 'text-emerald-500'">
                            {{ t.trxType }}{{ t.amount }} {{ t.code }}
                        </span>
                    </span>
                </button>

                <div v-if="expanded === i" class="bg-md-surface-container/50 px-4 py-3 text-sm">
                    <dl class="grid gap-2 sm:grid-cols-2">
                        <div class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Transaction ID</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.trx }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Wallet</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.code }}</dd>
                        </div>
                        <div v-if="t.charge > 0" class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Before charge</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.beforeCharge }} {{ t.code }}</dd>
                        </div>
                        <div v-if="t.charge > 0" class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Charge</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.chargeType }}{{ t.chargeFmt }} {{ t.code }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Transacted amount</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.amount }} {{ t.code }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Remaining balance</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.postBalance }} {{ t.code }}</dd>
                        </div>
                        <div v-if="t.receiver" class="flex justify-between gap-4 sm:block">
                            <dt class="text-md-on-surface-variant">Details</dt>
                            <dd class="font-medium text-md-on-surface">{{ t.details }} {{ t.receiver }}</dd>
                        </div>
                    </dl>
                </div>
            </li>
        </ul>
    </div>
</template>
