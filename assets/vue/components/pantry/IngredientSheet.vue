<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { LOCATION_ICONS, LOCATIONS, UNITS } from '../../pantry/locations.js';
import { defaultLowAt, MAX_TWELFTHS, TWELFTHS_PER_UNIT } from '../../pantry/quantity.js';
import BaseButton from '../ui/BaseButton.vue';
import BaseSelect from '../ui/BaseSelect.vue';
import BaseSwitch from '../ui/BaseSwitch.vue';
import BottomSheet from '../ui/BottomSheet.vue';
import ChipGroup from '../ui/ChipGroup.vue';
import ConfirmButton from '../ui/ConfirmButton.vue';
import FormField from '../ui/FormField.vue';
import FractionChips from './FractionChips.vue';
import QuantityStepper from './QuantityStepper.vue';

const props = defineProps({
    ingredient: { type: Object, default: null },
    initialName: { type: String, default: '' },
    initialLocation: { type: String, default: 'pantry' },
    allowAnother: { type: Boolean, default: true },
});
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['saved']);

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const name = ref('');
const location = ref('pantry');
const unit = ref('piece');
const manual = ref(false);
const lowAt = ref(defaultLowAt('piece'));
const whole = ref(TWELFTHS_PER_UNIT);
const fraction = ref(0);
const nameError = ref(null);
const saving = ref(false);
const nameInput = ref(null);

const editing = computed(() => props.ingredient !== null);
const capitalize = (text) => text.charAt(0).toLocaleUpperCase() + text.slice(1);
const locationOptions = computed(() => LOCATIONS.map((value) => ({ value, label: t(`pantry.location.${value}`), icon: LOCATION_ICONS[value] })));
const unitOptions = computed(() => UNITS.map((value) => ({ value, label: capitalize(t(`units.${value}.one`)) })));
const lowAtStep = computed(() => defaultLowAt(unit.value));

watch(open, (isOpen) => {
    if (!isOpen) return;
    nameError.value = null;
    if (props.ingredient) {
        ({ name: name.value, location: location.value, unit: unit.value, manual: manual.value, lowAt: lowAt.value } = props.ingredient);
        return;
    }
    name.value = props.initialName;
    location.value = props.initialLocation;
    unit.value = 'piece';
    manual.value = false;
    lowAt.value = defaultLowAt('piece');
    resetStock();
});

function resetStock() {
    whole.value = TWELFTHS_PER_UNIT;
    fraction.value = 0;
}

function changeUnit(next) {
    if (lowAt.value === defaultLowAt(unit.value)) lowAt.value = defaultLowAt(next);
    unit.value = next;
}

function focusName(event) {
    if (!editing.value && nameInput.value) {
        event.preventDefault();
        nameInput.value.focus();
    }
}

async function save(another) {
    saving.value = true;
    nameError.value = null;
    const body = { name: name.value, location: location.value, unit: unit.value, manual: manual.value, lowAt: lowAt.value, ...(editing.value ? {} : { stock: whole.value + fraction.value }) };
    try {
        if (editing.value) {
            emit('saved', await api.put(`/api/ingredients/${props.ingredient.id}`, body));
        } else {
            emit('saved', await api.post('/api/ingredients', body));
            toast.success(t('pantry.sheet.added', { name: body.name.trim() }));
        }
        if (another) {
            name.value = '';
            manual.value = false;
            lowAt.value = defaultLowAt(unit.value);
            resetStock();
            await nextTick();
            nameInput.value?.focus();
        } else {
            open.value = false;
        }
    } catch (error) {
        nameError.value = error.fieldErrors?.name ?? (error.status === 422 ? error.message : null);
        if (nameError.value === null) toast.error(error.message);
    } finally {
        saving.value = false;
    }
}

async function remove() {
    const removed = await toast.attempt(() => api.del(`/api/ingredients/${props.ingredient.id}`));
    if (removed) open.value = false;
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="editing ? t('pantry.sheet.editTitle') : t('pantry.sheet.createTitle')" @open-auto-focus="focusName">
        <form id="ingredient-form" class="ingredient-sheet" @submit.prevent="save(false)">
            <FormField :label="t('pantry.sheet.name')" :error="nameError">
                <input ref="nameInput" v-model="name" type="text" maxlength="60" autocomplete="off" />
            </FormField>
            <FormField :label="t('pantry.sheet.location')" as="div">
                <ChipGroup v-model="location" :options="locationOptions" :label="t('pantry.sheet.location')" />
            </FormField>
            <FormField :label="t('pantry.sheet.unit')">
                <BaseSelect :model-value="unit" :options="unitOptions" @update:model-value="changeUnit" />
            </FormField>
            <template v-if="!editing">
                <FormField :label="t('pantry.sheet.stock')" as="div">
                    <QuantityStepper v-model="whole" :label="t('pantry.sheet.stockWhole')" :decrease-label="t('pantry.sheet.stockLess')" :increase-label="t('pantry.sheet.stockMore')" :step="TWELFTHS_PER_UNIT" :max="MAX_TWELFTHS - TWELFTHS_PER_UNIT" />
                </FormField>
                <FormField :label="t('pantry.sheet.stockFraction')" as="div">
                    <FractionChips v-model="fraction" :label="t('pantry.sheet.stockFraction')" />
                </FormField>
            </template>
            <FormField :label="t('pantry.sheet.manual')" :hint="t('pantry.sheet.manualHint')" as="div">
                <BaseSwitch v-model="manual" :label="t('pantry.sheet.manual')" />
            </FormField>
            <FormField :label="t('pantry.sheet.lowAt')" as="div">
                <QuantityStepper v-model="lowAt" :label="t('pantry.sheet.lowAt')" :decrease-label="t('pantry.sheet.less')" :increase-label="t('pantry.sheet.more')" :step="lowAtStep" :max="MAX_TWELFTHS" />
            </FormField>
        </form>
        <template #footer>
            <span v-if="editing" class="ingredient-sheet__delete">
                <ConfirmButton :label="t('pantry.sheet.delete')" :title="t('pantry.sheet.deleteTitle', { name: ingredient.name })" :message="t('pantry.sheet.deleteMessage')" variant="danger" @confirm="remove" />
            </span>
            <BaseButton v-if="!editing && allowAnother" variant="secondary" :disabled="saving" @click="save(true)">{{ t('pantry.sheet.saveAndAdd') }}</BaseButton>
            <BaseButton type="submit" form="ingredient-form" :loading="saving">{{ t('pantry.sheet.save') }}</BaseButton>
        </template>
    </BottomSheet>
</template>

<style scoped>
.ingredient-sheet { display: flex; flex-direction: column; gap: var(--space-4); }
.ingredient-sheet__delete { margin-right: auto; }
</style>
