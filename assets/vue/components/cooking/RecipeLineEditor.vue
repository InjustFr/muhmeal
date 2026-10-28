<script setup>
import { computed } from 'vue';
import { PhTrash } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import { MAX_TWELFTHS, TWELFTHS_PER_UNIT } from '../../pantry/quantity.js';
import FractionChips from '../pantry/FractionChips.vue';
import QuantityStepper from '../pantry/QuantityStepper.vue';
import ChipGroup from '../ui/ChipGroup.vue';
import IconButton from '../ui/IconButton.vue';

const line = defineModel({ type: Object, required: true });
const emit = defineEmits(['remove']);

const { t } = useI18n();

const modes = computed(() => [
    { value: 'amount', label: t('recipes.edit.amount') },
    { value: 'some', label: t('recipes.edit.some') },
]);
const mode = computed({
    get: () => (line.value.some ? 'some' : 'amount'),
    set: (value) => { line.value = { ...line.value, some: value === 'some' }; },
});
const whole = computed({
    get: () => line.value.whole,
    set: (value) => { line.value = { ...line.value, whole: value }; },
});
const fraction = computed({
    get: () => line.value.fraction,
    set: (value) => { line.value = { ...line.value, fraction: value }; },
});
</script>

<template>
    <li class="editor" data-test="recipe-line">
        <div class="editor__head">
            <span class="editor__name">{{ line.name }}</span>
            <IconButton :icon="PhTrash" :label="t('recipes.edit.remove', { name: line.name })" variant="danger" @click="emit('remove')" />
        </div>
        <ChipGroup v-model="mode" variant="segmented" :options="modes" :label="t('recipes.edit.amountOf', { name: line.name })" />
        <div v-if="!line.some" class="editor__amount">
            <QuantityStepper v-model="whole" :label="t('recipes.edit.whole', { name: line.name })" :decrease-label="t('recipes.edit.less', { name: line.name })" :increase-label="t('recipes.edit.more', { name: line.name })" :step="TWELFTHS_PER_UNIT" :max="MAX_TWELFTHS - TWELFTHS_PER_UNIT" />
            <FractionChips v-model="fraction" :label="t('recipes.edit.fraction', { name: line.name })" />
        </div>
    </li>
</template>

<style scoped>
.editor { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); border: 0.125rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.editor__head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); }
.editor__name { min-width: 0; color: var(--color-ink); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; overflow-wrap: anywhere; }
.editor__amount { display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-3); }
</style>
