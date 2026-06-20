<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import KycStatusCard from '@/Components/User/KycStatusCard.vue';
import WalletCard from '@/Components/User/WalletCard.vue';
import InsightCards from '@/Components/User/InsightCards.vue';
import TransactionTable from '@/Components/User/TransactionTable.vue';
import Icon from '@/Components/Ui/Icon.vue';
import type {
    DashboardWallet,
    TotalBalance,
    DashboardKyc,
    DashboardTransaction,
} from '@/types/user';

defineProps<{
    wallets: DashboardWallet[];
    totalBalance: TotalBalance;
    insight: { in: number; out: number };
    kyc: DashboardKyc;
    histories: DashboardTransaction[];
    walletsUrl: string;
    insightUrl: string;
}>();
</script>

<template>
    <Head title="Dashboard" />

    <UserLayout>
        <div class="space-y-6">
            <KycStatusCard :kyc="kyc" />

            <!-- Top row: total balance + money flow -->
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl bg-md-primary p-6 text-md-on-primary">
                    <p class="text-sm opacity-80">Total balance</p>
                    <p class="mt-2 text-3xl font-semibold">{{ totalBalance.symbol }}{{ totalBalance.amount }}</p>
                    <p class="mt-1 text-sm opacity-80">{{ totalBalance.code }}</p>
                </div>
                <InsightCards
                    :money-in="insight.in"
                    :money-out="insight.out"
                    :symbol="totalBalance.symbol"
                    :insight-url="insightUrl"
                />
            </div>

            <!-- Wallets -->
            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-md-on-background">Wallets</h2>
                    <a :href="walletsUrl" class="inline-flex items-center gap-1 text-sm font-medium text-md-primary hover:underline">
                        More wallets <Icon name="arrow-up" size="1rem" />
                    </a>
                </div>
                <div v-if="wallets.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <WalletCard
                        v-for="(w, i) in wallets"
                        :key="i"
                        :wallet="w"
                        :show-transfer="!!w.transferUrl"
                    />
                </div>
                <p v-else class="rounded-2xl bg-md-surface p-6 text-md-on-surface-variant ring-1 ring-md-outline-variant/40">
                    No wallets yet.
                </p>
            </section>

            <!-- Latest transactions -->
            <section>
                <h2 class="mb-3 text-lg font-semibold text-md-on-background">Latest transactions</h2>
                <TransactionTable :transactions="histories" empty-message="No transactions yet." />
            </section>
        </div>
    </UserLayout>
</template>
