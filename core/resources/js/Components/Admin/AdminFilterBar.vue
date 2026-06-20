<script setup lang="ts">
import { reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export interface FilterField {
    name: string;
    label: string;
    type?: 'text' | 'select';
    options?: { value: string | number; label: string }[];
    placeholder?: string;
}

// Read-only GET filter bar. Submits the defined fields AND preserves any other existing
// query params (e.g. date/method set elsewhere) so the backend filter contract is unchanged.
const props = defineProps<{ fields: FilterField[]; values: Record<string, string> }>();

const base = (usePage().url || '').split('?')[0];
const form = reactive<Record<string, string>>({ ...props.values });

const inputClass =
    'w-full rounded-xl border border-md-outline-variant bg-md-surface px-3 py-2 text-sm text-md-on-surface focus:border-md-primary focus:outline-none';

function submit(): void {
    const existing = new URLSearchParams((usePage().url.split('?')[1] || ''));
    const params: Record<string, string> = {};
    existing.forEach((v, k) => {
        if (k !== 'page') params[k] = v;
    });
    for (const f of props.fields) params[f.name] = form[f.name] ?? '';
    Object.keys(params).forEach((k) => {
        if (!params[k]) delete params[k];
    });
    router.get(base, params, { preserveScroll: true, replace: true });
}
</script>

<template>
    <form class="flex flex-wrap items-end gap-3 rounded-2xl bg-md-surface p-4 ring-1 ring-md-outline-variant/40" @submit.prevent="submit">
        <div v-for="f in fields" :key="f.name" class="min-w-[10rem] flex-1">
            <label class="mb-1 block text-xs font-medium text-md-on-surface-variant">{{ f.label }}</label>
            <select v-if="f.type === 'select'" v-model="form[f.name]" :class="inputClass">
                <option value="">All</option>
                <option v-for="o in f.options" :key="o.value" :value="String(o.value)">{{ o.label }}</option>
            </select>
            <input v-else v-model="form[f.name]" type="text" :placeholder="f.placeholder" :class="inputClass" />
        </div>
        <button type="submit" class="h-[38px] rounded-xl bg-md-primary px-5 text-sm font-medium text-md-on-primary">Filter</button>
    </form>
</template>
