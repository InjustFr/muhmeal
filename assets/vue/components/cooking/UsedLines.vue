<script setup>
import { computed } from 'vue';
import { PhTrash } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import { currentLanguage } from '../../i18n/locale.js';
import { isShort, stepFor, stockAfter, withIngredient } from '../../cooking/lines.js';
import { describeQuantity, formatQuantity } from '../../pantry/quantity.js';
import IngredientPicker from '../pantry/IngredientPicker.vue';
import QuantityStepper from '../pantry/QuantityStepper.vue';
import IconButton from '../ui/IconButton.vue';

const lines = defineModel({ type: Array, required: true });

const { t } = useI18n();

const used = computed(() => lines.value.map((line) => line.ingredientId));

function setAmount(index, amount) {
    lines.value = lines.value.map((line, position) => (position === index ? { ...line, amount } : line));
}

function remove(index) {
    lines.value = lines.value.filter((_, position) => position !== index);
}

const preview = (line) => `${formatQuantity(line.stock, currentLanguage())} → ${describeQuantity(stockAfter(line), line.unit, currentLanguage(), t)}`;
</script>

<template>
    <div class="used">
        <p v-if="lines.length === 0" class="used__empty">{{ t('meals.lines.empty') }}</p>
        <ul v-else class="used__list">
            <li v-for="(line, index) in lines" :key="line.ingredientId" class="used__line" data-test="used-line">
                <div class="used__head">
                    <span class="used__name">{{ line.name }}</span>
                    <IconButton :icon="PhTrash" :label="t('meals.lines.remove', { name: line.name })" variant="danger" @click="remove(index)" />
                </div>
                <div class="used__body">
                    <QuantityStepper
                        :model-value="line.amount"
                        :label="t('meals.lines.group', { name: line.name })"
                        :decrease-label="t('meals.lines.less', { name: line.name })"
                        :increase-label="t('meals.lines.more', { name: line.name })"
                        :step="stepFor(line.unit)"
                        @update:model-value="(to) => setAmount(index, to)"
                    />
                    <span v-if="line.amount === 0" class="used__note" data-test="used-note">{{ t('meals.lines.notDeducted') }}</span>
                    <span v-else :class="['used__preview', 'tabular', { 'used__preview--short': isShort(line) }]" data-test="used-preview">{{ preview(line) }}</span>
                </div>
            </li>
        </ul>
        <IngredientPicker :exclude="used" @pick="(ingredient) => (lines = withIngredient(lines, ingredient))" />
    </div>
</template>

<style scoped>
.used { display: flex; flex-direction: column; gap: var(--space-3); }
.used__empty { padding: var(--space-3); border: 0.125rem dashed var(--color-border-strong); border-radius: var(--radius); color: var(--color-muted); text-align: center; }
.used__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.used__line { display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-2) var(--space-3); border: 0.125rem solid var(--color-border); border-radius: var(--radius); background: var(--color-bg); }
.used__head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); }
.used__name { min-width: 0; color: var(--color-ink); font-weight: 800; overflow-wrap: anywhere; }
.used__body { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-2); }
.used__note { color: var(--color-subtle); font-size: var(--font-size-sm); font-weight: 700; }
.used__preview { color: var(--color-muted); font-family: var(--font-display); font-size: var(--font-size-md); font-weight: 600; }
.used__preview--short { color: color-mix(in oklch, var(--color-warning) 45%, var(--color-ink)); }
</style>
