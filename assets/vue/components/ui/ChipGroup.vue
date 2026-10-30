<script setup>
import { computed } from 'vue';
import { ToggleGroupItem, ToggleGroupRoot } from 'reka-ui';

const props = defineProps({
    options: { type: Array, required: true },
    label: { type: String, required: true },
    variant: { type: String, default: 'chips' },
    multiple: { type: Boolean, default: false },
    allowNone: { type: Boolean, default: false },
});
const model = defineModel({ type: [String, Number, Array], required: true });

const selected = computed(() => (props.multiple ? model.value.map(String) : String(model.value)));

function choose(key) {
    if (props.multiple) {
        const next = props.options.filter((candidate) => key.includes(String(candidate.value))).map((candidate) => candidate.value);
        if (next.length > 0 || props.allowNone) model.value = next;
        return;
    }
    const option = props.options.find((candidate) => String(candidate.value) === key);
    if (option) model.value = option.value;
}
</script>

<template>
    <ToggleGroupRoot :type="multiple ? 'multiple' : 'single'" :model-value="selected" :aria-label="label" :class="['chips', `chips--${variant}`]" @update:model-value="choose">
        <ToggleGroupItem v-for="option in options" :key="option.value" :value="String(option.value)" :aria-label="option.ariaLabel" class="chips__item">
            <component :is="option.icon" v-if="option.icon" class="chips__icon" size="1.125rem" weight="bold" aria-hidden="true" />
            <span>{{ option.label }}</span>
        </ToggleGroupItem>
    </ToggleGroupRoot>
</template>

<style scoped>
.chips { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.chips--segmented { display: grid; grid-auto-columns: 1fr; grid-auto-flow: column; padding: var(--space-1); border: 0.125rem solid var(--color-border); border-radius: var(--radius-pill); background: var(--color-surface); }

.chips__item {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-1);
    min-width: var(--target);
    min-height: var(--target);
    padding: var(--space-1) var(--space-4);
    border: 0.125rem solid var(--color-border-strong);
    border-radius: var(--radius-pill);
    background: var(--color-surface);
    box-shadow: 0 var(--lip) 0 var(--color-border-strong);
    color: var(--color-ink);
    font-family: var(--font-display);
    font-size: var(--font-size-md);
    font-weight: 600;
    cursor: pointer;
    transition: transform 90ms ease, box-shadow 90ms ease, background var(--transition), border-color var(--transition);
}

.chips__item:hover { background: var(--color-hover); }
.chips__item:active { transform: translateY(var(--lip)); box-shadow: 0 0 0 var(--color-border-strong); }
.chips__item[data-state="on"] { border-color: var(--color-accent); background: var(--color-accent-soft); box-shadow: 0 var(--lip) 0 var(--color-accent); color: var(--color-accent-deep); }
.chips__item[data-state="on"]:active { box-shadow: 0 0 0 var(--color-accent); }
.chips__icon { flex-shrink: 0; }

.chips--segmented .chips__item { min-width: 0; padding: var(--space-1) var(--space-2); border-color: transparent; background: none; box-shadow: none; color: var(--color-muted); }
.chips--segmented .chips__item:active { transform: none; }
.chips--segmented .chips__item:hover { background: var(--color-hover); }
.chips--segmented .chips__item[data-state="on"] { border-color: var(--color-accent); background: var(--color-accent-soft); color: var(--color-accent-deep); }
</style>
