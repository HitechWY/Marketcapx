<script setup lang="ts">
// Thin wrapper over the Material Symbols font (loaded in the Inertia root view).
// Maps semantic nav keys to Material Symbol glyph names.
const map: Record<string, string> = {
    dashboard: 'dashboard',
    history: 'history',
    wallet: 'account_balance_wallet',
    receipt: 'receipt_long',
    'add-money': 'add_card',
    withdraw: 'payments',
    transfer: 'swap_horiz',
    'money-out': 'call_made',
    payment: 'shopping_bag',
    exchange: 'currency_exchange',
    request: 'request_quote',
    invoice: 'receipt',
    voucher: 'redeem',
    escrow: 'gavel',
    profile: 'person',
    lock: 'lock',
    shield: 'shield',
    qr: 'qr_code_2',
    logout: 'logout',
    'arrow-up': 'arrow_upward',
    'arrow-down': 'arrow_downward',
    people: 'group',
    store: 'storefront',
    agent: 'support_agent',
    ticket: 'confirmation_number',
    report: 'summarize',
    list: 'list_alt',
    menu: 'menu',
    close: 'close',
    sun: 'light_mode',
    moon: 'dark_mode',
};

const props = withDefaults(defineProps<{ name: string; size?: string }>(), { size: '1.25rem' });
</script>

<template>
    <span class="material-symbols-outlined" :style="{ fontSize: props.size, lineHeight: 1 }" aria-hidden="true">{{
        map[props.name] || 'circle'
    }}</span>
</template>
