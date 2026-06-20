<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/Components/Ui/Icon.vue';

// Money-in / money-out summary with a day-range filter. The range refetches the
// existing read-only JSON endpoint (user.check.insight) — no chart library (deferred).
const props = defineProps<{ moneyIn: number; moneyOut: number; symbol: string; insightUrl: string }>();

const days = ref(7);
const valueIn = ref(props.moneyIn);
const valueOut = ref(props.moneyOut);
const loading = ref(false);

const ranges = [
    { label: '7D', v: 7 },
    { label: '15D', v: 15 },
    { label: '30D', v: 31 },
    { label: '1Y', v: 365 },
];

function fmt(n: number): string {
    return Number(n || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
}

async function load(d: number): Promise<void> {
    days.value = d;
    loading.value = true;
    try {
        const res = await fetch(`${props.insightUrl}?day=${d}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        const data = await res.json();
        if (typeof data.totalMoneyIn !== 'undefined') {
            valueIn.value = data.totalMoneyIn;
            valueOut.value = data.totalMoneyOut;
        }
    } catch {
        /* keep previous values on failure */
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-md-on-surface">Money flow</h3>
            <div class="flex gap-1">
                <button
                    v-for="r in ranges"
                    :key="r.v"
                    type="button"
                    class="rounded-lg px-2.5 py-1 text-xs font-medium transition"
                    :class="days === r.v ? 'bg-md-primary text-md-on-primary' : 'text-md-on-surface-variant hover:bg-md-on-surface/5'"
                    @click="load(r.v)"
                >
                    {{ r.label }}
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4" :class="{ 'opacity-50': loading }">
            <div class="rounded-xl bg-md-surface-container p-4">
                <div class="flex items-center gap-2 text-emerald-500">
                    <Icon name="arrow-down" size="1.1rem" />
                    <span class="text-xs font-medium uppercase tracking-wide">Money In</span>
                </div>
                <p class="mt-2 text-xl font-semibold text-md-on-surface">{{ symbol }}{{ fmt(valueIn) }}</p>
            </div>
            <div class="rounded-xl bg-md-surface-container p-4">
                <div class="flex items-center gap-2 text-md-error">
                    <Icon name="arrow-up" size="1.1rem" />
                    <span class="text-xs font-medium uppercase tracking-wide">Money Out</span>
                </div>
                <p class="mt-2 text-xl font-semibold text-md-on-surface">{{ symbol }}{{ fmt(valueOut) }}</p>
            </div>
        </div>
    </div>
</template>
