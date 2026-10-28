<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage } from '../../i18n/locale.js';
import { describeQuantity, MAX_TWELFTHS, splitQuantity, TWELFTHS_PER_UNIT } from '../../pantry/quantity.js';
import BaseButton from '../ui/BaseButton.vue';
import BottomSheet from '../ui/BottomSheet.vue';
import FormField from '../ui/FormField.vue';
import FractionChips from './FractionChips.vue';
import QuantityStepper from './QuantityStepper.vue';

const props = defineProps({
    ingredient: { type: Object, default: null },
});
const open = defineModel('open', { type: Boolean, required: true });

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const whole = ref(0);
const fraction = ref(0);
const total = computed(() => whole.value + fraction.value);
const summary = computed(() => (props.ingredient ? describeQuantity(total.value, props.ingredient.unit, currentLanguage(), t) : ''));
const saving = ref(false);

watch(open, (isOpen) => {
    if (isOpen && props.ingredient) {
        ({ whole: whole.value, fraction: fraction.value } = splitQuantity(props.ingredient.stock));
    }
});

async function save() {
    saving.value = true;
    const saved = await toast.attempt(() => api.put(`/api/ingredients/${props.ingredient.id}/stock`, { stock: total.value }));
    saving.value = false;
    if (saved) open.value = false;
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="t('pantry.stock.title', { name: ingredient?.name ?? '' })">
        <template v-if="ingredient">
            <p class="stock-sheet__summary" data-test="stock-summary">{{ summary }}</p>
            <FormField :label="t('pantry.stock.whole')" as="div">
                <QuantityStepper v-model="whole" :label="t('pantry.stock.whole')" :decrease-label="t('pantry.stock.less')" :increase-label="t('pantry.stock.more')" :step="TWELFTHS_PER_UNIT" :max="MAX_TWELFTHS - TWELFTHS_PER_UNIT" />
            </FormField>
            <FormField :label="t('pantry.stock.fraction')" as="div">
                <FractionChips v-model="fraction" :label="t('pantry.stock.fraction')" />
            </FormField>
        </template>
        <template #footer>
            <BaseButton :loading="saving" @click="save">{{ t('pantry.stock.save') }}</BaseButton>
        </template>
    </BottomSheet>
</template>

<style scoped>
.stock-sheet__summary { color: var(--color-accent-deep); font-family: var(--font-display); font-size: 2rem; font-weight: 600; text-align: center; }
</style>
