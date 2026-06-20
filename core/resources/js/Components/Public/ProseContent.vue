<script setup lang="ts">
// Themed wrapper for admin-authored CMS HTML (policy/cookie bodies, etc.).
// Content is trusted admin HTML — same trust model as the Blade raw echoes
// (`@php echo $...->details @endphp`) it replaces — rendered via v-html.
defineProps<{ html: string | null }>();
</script>

<template>
    <div class="md-prose" v-html="html || ''"></div>
</template>

<style scoped>
.md-prose {
    color: var(--md-sys-color-on-surface);
    line-height: 1.7;
    font-size: 0.975rem;
}
.md-prose :deep(h1),
.md-prose :deep(h2),
.md-prose :deep(h3),
.md-prose :deep(h4) {
    color: var(--md-sys-color-on-surface);
    font-weight: 600;
    line-height: 1.25;
    margin: 1.6em 0 0.6em;
}
.md-prose :deep(h1) { font-size: 1.7rem; }
.md-prose :deep(h2) { font-size: 1.4rem; }
.md-prose :deep(h3) { font-size: 1.2rem; }
.md-prose :deep(p) { margin: 0 0 1em; }
.md-prose :deep(ul),
.md-prose :deep(ol) { margin: 0 0 1em; padding-left: 1.5em; }
.md-prose :deep(ul) { list-style: disc; }
.md-prose :deep(ol) { list-style: decimal; }
.md-prose :deep(li) { margin: 0.35em 0; }
.md-prose :deep(a) { color: var(--md-sys-color-primary); text-decoration: underline; }
.md-prose :deep(a:hover) { opacity: 0.85; }
.md-prose :deep(img) { max-width: 100%; height: auto; border-radius: 0.75rem; }
.md-prose :deep(blockquote) {
    border-left: 3px solid var(--md-sys-color-primary);
    padding-left: 1em;
    margin: 1em 0;
    color: var(--md-sys-color-on-surface-variant);
}
.md-prose :deep(table) { width: 100%; border-collapse: collapse; margin: 1em 0; }
.md-prose :deep(th),
.md-prose :deep(td) {
    border: 1px solid var(--md-sys-color-outline-variant);
    padding: 0.5em 0.75em;
    text-align: left;
}
.md-prose :deep(strong) { color: var(--md-sys-color-on-surface); font-weight: 600; }
.md-prose :deep(hr) { border: 0; border-top: 1px solid var(--md-sys-color-outline-variant); margin: 1.5em 0; }
</style>
