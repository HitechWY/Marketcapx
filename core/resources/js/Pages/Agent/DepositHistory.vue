<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgentLayout from '@/Layouts/AgentLayout.vue';
import PanelDepositHistory from '@/Components/Panel/PanelDepositHistory.vue';
import type { DepositRow } from '@/types/user';
import type { Paginator } from '@/types/public';

defineProps<{ deposits: Paginator<DepositRow> }>();
</script>

<template>
    <Head title="Add Money History" />

    <AgentLayout>
        <PanelDepositHistory :deposits="deposits" :searchable="false" />
    </AgentLayout>
</template>
