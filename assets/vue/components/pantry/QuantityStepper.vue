<script setup>
import { PhMinus, PhPlus } from '@phosphor-icons/vue';
import { currentLanguage } from '../../i18n/locale.js';
import { formatQuantity, MAX_TWELFTHS } from '../../pantry/quantity.js';

const props = defineProps({
    label: { type: String, required: true },
    decreaseLabel: { type: String, required: true },
    increaseLabel: { type: String, required: true },
    step: { type: Number, default: 12 },
    min: { type: Number, default: 0 },
    max: { type: Number, default: MAX_TWELFTHS },
});
const model = defineModel({ type: Number, required: true });

const move = (direction) => {
    model.value = Math.min(props.max, Math.max(props.min, model.value + direction * props.step));
};
</script>

<template>
    <div class="stepper" role="group" :aria-label="label">
        <button type="button" class="stepper__button" :aria-label="decreaseLabel" :disabled="model <= min" @click="move(-1)">
            <PhMinus size="1.25rem" weight="bold" aria-hidden="true" />
        </button>
        <slot name="value">
            <output class="stepper__value tabular">{{ formatQuantity(model, currentLanguage()) }}</output>
        </slot>
        <button type="button" class="stepper__button" :aria-label="increaseLabel" :disabled="model >= max" @click="move(1)">
            <PhPlus size="1.25rem" weight="bold" aria-hidden="true" />
        </button>
    </div>
</template>

<style scoped>
.stepper { display: inline-flex; align-items: center; gap: var(--space-1); }
.stepper__value { min-width: 3rem; color: var(--color-ink); font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; text-align: center; }

.stepper__button {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    width: var(--target);
    height: var(--target);
    border: 0.125rem solid var(--color-accent-deep);
    border-radius: var(--radius-pill);
    background: var(--color-accent);
    box-shadow: 0 var(--lip) 0 var(--color-accent-deep);
    color: var(--color-on-accent);
    cursor: pointer;
    transition: transform 90ms ease, box-shadow 90ms ease, opacity var(--transition);
}

.stepper__button:active:not(:disabled) { transform: translateY(var(--lip)); box-shadow: 0 0 0 var(--color-accent-deep); }
.stepper__button:disabled { border-color: var(--color-border-strong); background: var(--color-border); box-shadow: 0 var(--lip) 0 var(--color-border-strong); color: var(--color-subtle); cursor: not-allowed; opacity: 0.6; }
</style>
