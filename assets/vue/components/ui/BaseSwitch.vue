<script setup>
import { SwitchRoot, SwitchThumb } from 'reka-ui';

defineOptions({ inheritAttrs: false });

defineProps({
    label: { type: String, required: true },
});
const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <SwitchRoot v-model="model" class="switch" :aria-label="label" v-bind="$attrs">
        <SwitchThumb class="switch__thumb" />
    </SwitchRoot>
</template>

<style scoped>
.switch { display: inline-flex; align-items: center; flex-shrink: 0; width: 3.5rem; height: var(--target); padding: 0; border: 0.125rem solid var(--color-border-strong); border-radius: var(--radius-pill); background: var(--color-border); cursor: pointer; transition: background var(--transition), border-color var(--transition); }
.switch[data-state="checked"] { border-color: var(--color-accent-deep); background: var(--color-accent); }
.switch__thumb { display: block; width: 1.75rem; height: 1.75rem; border-radius: var(--radius-pill); background: var(--color-surface); box-shadow: 0 0.125rem 0.25rem rgb(92 60 20 / 25%); transform: translateX(0.125rem); transition: transform var(--transition); }
.switch__thumb[data-state="checked"] { transform: translateX(1.375rem); }
</style>
