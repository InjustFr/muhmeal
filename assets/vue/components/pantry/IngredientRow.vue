<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { currentLanguage } from '../../i18n/locale.js';
import { LOCATION_ICONS } from '../../pantry/locations.js';
import { describeQuantity, TWELFTHS_PER_UNIT } from '../../pantry/quantity.js';
import QuantityStepper from './QuantityStepper.vue';

const props = defineProps({
    ingredient: { type: Object, required: true },
});
const emit = defineEmits(['edit', 'stock', 'move']);

const { t } = useI18n();

const quantity = computed(() => describeQuantity(props.ingredient.stock, props.ingredient.unit, currentLanguage(), t));
</script>

<template>
    <li :class="['row', { 'row--out': ingredient.out }]" data-test="ingredient-row">
        <span :class="['row__icon', `row__icon--${ingredient.location}`]" aria-hidden="true">
            <component :is="LOCATION_ICONS[ingredient.location]" size="1.25rem" weight="duotone" />
        </span>
        <div class="row__text">
            <button type="button" class="row__name" @click="emit('edit')">{{ ingredient.name }}<span class="visually-hidden"> ({{ t('pantry.row.edit') }})</span></button>
            <span v-if="ingredient.out" class="row__badge row__badge--out">{{ t('pantry.badge.out') }}</span>
            <span v-else-if="ingredient.runningLow" class="row__badge row__badge--low">{{ t('pantry.badge.low') }}</span>
        </div>
        <QuantityStepper
            :model-value="ingredient.stock"
            :label="t('pantry.row.group', { name: ingredient.name })"
            :decrease-label="t('pantry.row.decrease', { name: ingredient.name })"
            :increase-label="t('pantry.row.increase', { name: ingredient.name })"
            :step="TWELFTHS_PER_UNIT"
            @update:model-value="(to) => emit('move', to)"
        >
            <template #value>
                <button type="button" class="row__quantity tabular" @click="emit('stock')">
                    <span class="visually-hidden">{{ t('pantry.row.setStock', { name: ingredient.name }) }}: </span>{{ quantity }}
                </button>
            </template>
        </QuantityStepper>
    </li>
</template>

<style scoped>
.row { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-3); border: 0.125rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); transition: opacity var(--transition); }
.row--out { opacity: 0.6; }

.row__icon { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: var(--radius-pill); color: var(--color-ink); }
.row__icon--fridge { background: var(--location-fridge); }
.row__icon--freezer { background: var(--location-freezer); }
.row__icon--pantry { background: var(--location-pantry); }

.row__text { display: flex; flex: 1; flex-wrap: wrap; align-items: center; column-gap: var(--space-2); min-width: 0; }
.row__name { max-width: 100%; min-width: var(--target); min-height: var(--target); padding: 0; border: none; background: none; color: var(--color-ink); font-size: var(--font-size); font-weight: 700; text-align: left; overflow-wrap: anywhere; cursor: pointer; }
.row__name:hover { text-decoration: underline; text-underline-offset: 0.1875rem; }

.row__badge { padding: 0 var(--space-2); border-radius: var(--radius-pill); font-size: var(--font-size-xs); font-weight: 800; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.row__badge--out { background: var(--color-danger-soft); color: var(--color-danger); }
.row__badge--low { background: var(--color-highlight-soft); color: var(--color-ink); }

.row__quantity { min-width: 4.5rem; min-height: var(--target); padding: 0 var(--space-1); border: none; border-radius: var(--radius); background: none; color: var(--color-ink); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; text-align: center; cursor: pointer; }
.row__quantity:hover { background: var(--color-hover); }

@media (max-width: 26rem) {
    .row__icon { display: none; }
}
</style>
