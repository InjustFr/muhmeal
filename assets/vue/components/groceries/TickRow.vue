<script setup>
import { PhCheck } from '@phosphor-icons/vue';
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui';

defineProps({
    label: { type: String, required: true },
    detail: { type: String, default: null },
    out: { type: Boolean, default: false },
    outLabel: { type: String, default: null },
    crossOut: { type: Boolean, default: true },
});
const model = defineModel({ type: Boolean, required: true });
</script>

<template>
    <CheckboxRoot v-model="model" :class="['tick', { 'tick--on': model, 'tick--done': model && crossOut }]" data-test="tick-row">
        <span class="tick__box" aria-hidden="true">
            <CheckboxIndicator class="tick__check"><PhCheck size="1.25rem" weight="bold" /></CheckboxIndicator>
        </span>
        <span class="tick__label">{{ label }}</span>
        <span v-if="out" class="tick__badge">{{ outLabel }}</span>
        <span v-else-if="detail" class="tick__detail">{{ detail }}</span>
    </CheckboxRoot>
</template>

<style scoped>
.tick {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    width: 100%;
    min-height: 3.5rem;
    padding: var(--space-2) var(--space-4);
    border: 0.125rem solid var(--color-border);
    border-radius: var(--radius);
    background: var(--color-surface);
    box-shadow: 0 var(--lip) 0 var(--color-border);
    color: var(--color-ink);
    text-align: left;
    cursor: pointer;
    transition: transform 90ms ease, box-shadow 90ms ease, background var(--transition), border-color var(--transition), opacity var(--transition);
}

.tick:hover { background: var(--color-hover); }
.tick:active { transform: translateY(var(--lip)); box-shadow: 0 0 0 var(--color-border); }
.tick--on { border-color: var(--color-accent); }
.tick--done { border-color: var(--color-accent-soft); background: var(--color-bg); box-shadow: none; opacity: 0.75; }
.tick--done:active { transform: none; }

.tick__box { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2rem; height: 2rem; border: 0.1875rem solid var(--color-border-strong); border-radius: var(--radius-sm); background: var(--color-surface); color: var(--color-on-accent); transition: background var(--transition), border-color var(--transition); }
.tick--on .tick__box { border-color: var(--color-accent-deep); background: var(--color-accent); }
.tick__check { display: inline-flex; }

.tick__label { flex: 1; min-width: 0; font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; overflow-wrap: anywhere; }
.tick--done .tick__label { color: var(--color-muted); text-decoration: line-through; text-decoration-thickness: 0.125rem; }

.tick__detail { flex-shrink: 0; color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 700; }
.tick__badge { flex-shrink: 0; padding: 0 var(--space-2); border-radius: var(--radius-pill); background: var(--color-danger-soft); color: var(--color-danger); font-size: var(--font-size-xs); font-weight: 800; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
</style>
