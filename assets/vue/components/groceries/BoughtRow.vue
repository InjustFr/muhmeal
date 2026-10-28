<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { boughtMax, boughtStep, stockAfterBuying } from '../../groceries/lists.js';
import { currentLanguage } from '../../i18n/locale.js';
import { describeQuantity, formatQuantity } from '../../pantry/quantity.js';
import QuantityStepper from '../pantry/QuantityStepper.vue';

const props = defineProps({
    line: { type: Object, required: true },
});
const emit = defineEmits(['change']);

const { t } = useI18n();

const preview = computed(() => (props.line.amount === 0
    ? t('groceries.putAway.none')
    : `${formatQuantity(props.line.stock, currentLanguage())} → ${describeQuantity(stockAfterBuying(props.line), props.line.unit, currentLanguage(), t)}`));
</script>

<template>
    <li class="bought" data-test="bought-line">
        <span class="bought__name">{{ line.name }}</span>
        <div class="bought__body">
            <QuantityStepper
                :model-value="line.amount"
                :label="t('groceries.putAway.group', { name: line.name })"
                :decrease-label="t('groceries.putAway.less', { name: line.name })"
                :increase-label="t('groceries.putAway.more', { name: line.name })"
                :step="boughtStep(line)"
                :max="boughtMax(line)"
                @update:model-value="(to) => emit('change', to)"
            />
            <span class="bought__preview tabular" data-test="bought-preview">{{ preview }}</span>
        </div>
    </li>
</template>

<style scoped>
.bought { display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-2) var(--space-4); border: 0.125rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.bought__name { color: var(--color-ink); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; overflow-wrap: anywhere; }
.bought__body { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-2); }
.bought__preview { color: var(--color-accent-strong); font-family: var(--font-display); font-size: var(--font-size); font-weight: 600; }
</style>
