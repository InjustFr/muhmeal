<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage } from '../../i18n/locale.js';
import { linesFromRecipe, stepFor, stockAfter, usedOf } from '../../cooking/lines.js';
import { describeQuantity, formatQuantity, MAX_TWELFTHS } from '../../pantry/quantity.js';
import QuantityStepper from '../pantry/QuantityStepper.vue';
import BaseButton from '../ui/BaseButton.vue';
import BottomSheet from '../ui/BottomSheet.vue';
import FormField from '../ui/FormField.vue';
import CookSuccess from './CookSuccess.vue';
import UsedLines from './UsedLines.vue';

const props = defineProps({
    recipe: { type: Object, default: null },
});
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['made']);

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const made = ref(0);
const usedLines = ref([]);
const saving = ref(false);
const done = ref(null);

const output = computed(() => props.recipe?.output ?? null);
const step = computed(() => stepFor(output.value?.unit ?? 'piece'));
const used = computed(() => usedOf(usedLines.value));
const preview = computed(() => (output.value ? `${formatQuantity(output.value.stock, currentLanguage())} → ${describeQuantity(Math.min(MAX_TWELFTHS, output.value.stock + made.value), output.value.unit, currentLanguage(), t)}` : ''));

watch(open, (isOpen) => {
    if (!isOpen || !props.recipe) return;
    made.value = props.recipe.output.yield;
    usedLines.value = linesFromRecipe(props.recipe);
    done.value = null;
});

async function submit() {
    saving.value = true;
    try {
        await api.post(`/api/recipes/${props.recipe.id}/make`, { made: made.value, used: used.value });
        done.value = {
            rows: [
                ...usedLines.value.filter((line) => line.amount > 0).map((line) => ({ name: line.name, unit: line.unit, from: line.stock, to: stockAfter(line) })),
                { name: output.value.name, unit: output.value.unit, from: output.value.stock, to: Math.min(MAX_TWELFTHS, output.value.stock + made.value) },
            ],
        };
        toast.success(t('meals.toast.made', { name: output.value.name }));
        emit('made');
    } catch (error) {
        toast.error(error.message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="t('meals.prep.title', { name: recipe?.name ?? '' })">
        <template v-if="recipe">
            <CookSuccess v-if="done" :rows="done.rows" :headline="t('meals.done.prepTitle')" />
            <template v-else>
                <FormField :label="t('meals.prep.makes')" as="div">
                    <p class="prep__output">{{ output.name }}</p>
                    <div class="prep__made">
                        <QuantityStepper v-model="made" :label="t('meals.prep.made')" :decrease-label="t('meals.prep.less')" :increase-label="t('meals.prep.more')" :step="step" :min="step" :max="MAX_TWELFTHS" />
                        <span class="prep__preview tabular" data-test="prep-preview">{{ preview }}</span>
                    </div>
                </FormField>
                <FormField :label="t('meals.lines.label')" as="div">
                    <UsedLines v-model="usedLines" />
                </FormField>
            </template>
        </template>
        <template #footer>
            <BaseButton v-if="done" @click="open = false">{{ t('meals.done.close') }}</BaseButton>
            <BaseButton v-else :loading="saving" :disabled="made <= 0" data-test="submit-prep" @click="submit">{{ t('meals.prep.submit') }}</BaseButton>
        </template>
    </BottomSheet>
</template>

<style scoped>
.prep__output { color: var(--color-ink); font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; }
.prep__made { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-2); }
.prep__preview { color: var(--color-accent-strong); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; }
</style>
