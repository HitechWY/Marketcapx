<script setup lang="ts">
import { computed } from 'vue';
import { Line, Bar, Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    ArcElement,
    Filler,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler);

// Thin vue-chartjs wrapper for the admin dashboard (read-only). Renders line/bar/doughnut.
const props = withDefaults(
    defineProps<{ type: 'line' | 'bar' | 'doughnut'; data: unknown; options?: Record<string, unknown> }>(),
    { options: undefined },
);

const cmp = computed(() => (props.type === 'line' ? Line : props.type === 'bar' ? Bar : Doughnut));

const merged = computed(() =>
    props.options ?? {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
    },
);
</script>

<template>
    <div class="relative h-72">
        <component :is="cmp" :data="data as any" :options="merged as any" />
    </div>
</template>
