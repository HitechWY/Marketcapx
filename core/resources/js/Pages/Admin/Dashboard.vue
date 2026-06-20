<script setup lang="ts">
import { defineAsyncComponent, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminMetricCard from '@/Components/Admin/AdminMetricCard.vue';

// F7c: lazy-load the chart layer so chart.js/vue-chartjs land in an admin-only async chunk
// (fetched only when the admin dashboard mounts), keeping them out of the main app bundle.
const AdminChart = defineAsyncComponent(() => import('@/Components/Admin/AdminChart.vue'));
import type { AdminWidget, AdminMoneySummary, AdminLoginCharts, AdminChartEndpoints, AdminChartResponse } from '@/types/admin';

const props = defineProps<{
    widget: AdminWidget;
    deposits: AdminMoneySummary;
    withdrawals: AdminMoneySummary;
    loginCharts: AdminLoginCharts;
    chartEndpoints: AdminChartEndpoints;
    currencySymbol: string;
}>();

const PALETTE = ['#6750A4', '#386A20', '#B3261E', '#1D6FB8', '#7D5260', '#7A5900', '#00696E', '#9A25AE'];

const money = (v: string) => `${props.currencySymbol}${v}`;

function doughnut(rec: Record<string, number>) {
    return {
        labels: Object.keys(rec),
        datasets: [{ data: Object.values(rec), backgroundColor: PALETTE, borderWidth: 0 }],
    };
}

function timeSeries(resp: AdminChartResponse, kind: 'line' | 'bar') {
    return {
        labels: resp.created_on,
        datasets: resp.data.map((s, i) => ({
            label: s.name,
            data: s.data,
            borderColor: PALETTE[i % PALETTE.length],
            backgroundColor: kind === 'bar' ? PALETTE[i % PALETTE.length] : `${PALETTE[i % PALETTE.length]}33`,
            tension: 0.3,
            fill: kind === 'line',
            borderWidth: 2,
        })),
    };
}

const rangeDays = ref(30);
const depositWithdraw = ref<ReturnType<typeof timeSeries> | null>(null);
const transactions = ref<ReturnType<typeof timeSeries> | null>(null);
const chargeCommission = ref<ReturnType<typeof timeSeries> | null>(null);
const registration = ref<ReturnType<typeof timeSeries> | null>(null);
const loading = ref(false);

function isoDate(d: Date): string {
    return d.toISOString().slice(0, 10);
}

async function fetchSeries(url: string): Promise<AdminChartResponse | null> {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - rangeDays.value);
    const qs = `?start_date=${isoDate(start)}&end_date=${isoDate(end)}`;
    try {
        const res = await fetch(url + qs, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, credentials: 'same-origin' });
        if (!res.ok) return null;
        return (await res.json()) as AdminChartResponse;
    } catch {
        return null;
    }
}

async function loadCharts(): Promise<void> {
    loading.value = true;
    const [dw, tx, cc, reg] = await Promise.all([
        fetchSeries(props.chartEndpoints.depositWithdraw),
        fetchSeries(props.chartEndpoints.transaction),
        fetchSeries(props.chartEndpoints.chargeCommission),
        fetchSeries(props.chartEndpoints.registration),
    ]);
    depositWithdraw.value = dw ? timeSeries(dw, 'bar') : null;
    transactions.value = tx ? timeSeries(tx, 'line') : null;
    chargeCommission.value = cc ? timeSeries(cc, 'line') : null;
    registration.value = reg ? timeSeries(reg, 'line') : null;
    loading.value = false;
}

function setRange(days: number): void {
    rangeDays.value = days;
    loadCharts();
}

onMounted(loadCharts);
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <div class="space-y-8">
            <h1 class="text-2xl font-semibold text-md-on-background">Dashboard</h1>

            <!-- Users -->
            <section class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-md-on-surface-variant/70">Users</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <AdminMetricCard label="Total Users" :value="widget.total_users" icon="people" />
                    <AdminMetricCard label="Active Users" :value="widget.verified_users" icon="people" />
                    <AdminMetricCard label="Email Unverified" :value="widget.email_unverified_users" icon="people" />
                    <AdminMetricCard label="Mobile Unverified" :value="widget.mobile_unverified_users" icon="people" />
                </div>
            </section>

            <!-- Agents & Merchants -->
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="space-y-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-md-on-surface-variant/70">Agents</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AdminMetricCard label="Total Agents" :value="widget.total_agents" icon="agent" />
                        <AdminMetricCard label="Active Agents" :value="widget.verified_agents" icon="agent" />
                    </div>
                </div>
                <div class="space-y-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-md-on-surface-variant/70">Merchants</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AdminMetricCard label="Total Merchants" :value="widget.total_merchants" icon="store" />
                        <AdminMetricCard label="Active Merchants" :value="widget.verified_merchants" icon="store" />
                    </div>
                </div>
            </section>

            <!-- Deposits & Withdrawals -->
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <AdminMetricCard label="Deposited" :value="money(deposits.amount)" icon="receipt" :hint="`${deposits.pending} pending · ${deposits.rejected} rejected`" />
                <AdminMetricCard label="Deposit Charge" :value="money(deposits.charge)" icon="receipt" />
                <AdminMetricCard label="Withdrawn" :value="money(withdrawals.amount)" icon="withdraw" :hint="`${withdrawals.pending} pending · ${withdrawals.rejected} rejected`" />
                <AdminMetricCard label="Withdraw Charge" :value="money(withdrawals.charge)" icon="withdraw" />
            </section>

            <!-- Time-series charts -->
            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wider text-md-on-surface-variant/70">Reports</h2>
                    <div class="flex gap-1.5">
                        <button
                            v-for="d in [7, 30, 90]"
                            :key="d"
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                            :class="rangeDays === d ? 'bg-md-primary text-md-on-primary' : 'bg-md-surface text-md-on-surface-variant ring-1 ring-md-outline-variant/40 hover:bg-md-on-surface/5'"
                            @click="setRange(d)"
                        >
                            {{ d }}d
                        </button>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">Deposits & Withdrawals</p>
                        <AdminChart v-if="depositWithdraw" type="bar" :data="depositWithdraw" />
                        <p v-else class="py-12 text-center text-sm text-md-on-surface-variant">{{ loading ? 'Loading…' : 'No data' }}</p>
                    </div>
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">Transactions</p>
                        <AdminChart v-if="transactions" type="line" :data="transactions" />
                        <p v-else class="py-12 text-center text-sm text-md-on-surface-variant">{{ loading ? 'Loading…' : 'No data' }}</p>
                    </div>
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">Charge & Commission</p>
                        <AdminChart v-if="chargeCommission" type="line" :data="chargeCommission" />
                        <p v-else class="py-12 text-center text-sm text-md-on-surface-variant">{{ loading ? 'Loading…' : 'No data' }}</p>
                    </div>
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">Registrations</p>
                        <AdminChart v-if="registration" type="line" :data="registration" />
                        <p v-else class="py-12 text-center text-sm text-md-on-surface-variant">{{ loading ? 'Loading…' : 'No data' }}</p>
                    </div>
                </div>
            </section>

            <!-- Login distribution (last 30 days) -->
            <section class="space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-md-on-surface-variant/70">Logins (30 days)</h2>
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">By Browser</p>
                        <AdminChart type="doughnut" :data="doughnut(loginCharts.browser)" />
                    </div>
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">By OS</p>
                        <AdminChart type="doughnut" :data="doughnut(loginCharts.os)" />
                    </div>
                    <div class="rounded-2xl bg-md-surface p-5 ring-1 ring-md-outline-variant/40">
                        <p class="mb-3 text-sm font-medium text-md-on-surface">By Country (top 5)</p>
                        <AdminChart type="doughnut" :data="doughnut(loginCharts.country)" />
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
