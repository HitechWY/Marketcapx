<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserLayout from '@/Layouts/UserLayout.vue';
import PanelDepositHistory from '@/Components/Panel/PanelDepositHistory.vue';
import type { DepositRow } from '@/types/user';
import type { Paginator } from '@/types/public';

defineProps<{ deposits: Paginator<DepositRow>; search: string }>();
</script>

<template>
    <Head title="Deposit History" />

    <UserLayout>
        <PanelDepositHistory :deposits="deposits" :search="search" />
    </UserLayout>
</template>
