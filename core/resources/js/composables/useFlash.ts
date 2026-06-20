import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Surfaces the shared `flash.notify` ([status, message] tuples from session('notify'))
// as auto-dismissing toasts, preserving the existing notify behaviour on Inertia pages
// (the Blade iziToast partial isn't loaded on the Inertia root). Call once (FlashToasts).

export interface Toast {
    id: number;
    type: string;
    message: string;
}

const toasts = ref<Toast[]>([]);
let seq = 0;
let initialized = false;

function dismiss(id: number): void {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

function emit(notify: unknown): void {
    if (!Array.isArray(notify)) {
        return;
    }
    for (const entry of notify) {
        if (!Array.isArray(entry)) {
            continue;
        }
        const [type, message] = entry as [string, string];
        const id = ++seq;
        toasts.value.push({ id, type: type || 'info', message: message ?? '' });
        setTimeout(() => dismiss(id), 5000);
    }
}

export function useFlash() {
    const page = usePage();
    if (!initialized) {
        initialized = true;
        emit((page.props as Record<string, any>)?.flash?.notify);
        watch(
            () => (page.props as Record<string, any>)?.flash?.notify,
            (n) => emit(n),
        );
    }
    return { toasts, dismiss };
}
