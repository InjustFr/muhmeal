<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { useDates } from '../../composables/useDates.js';
import { useSession } from '../../composables/useSession.js';
import { useToast } from '../../composables/useToast.js';
import { addDays } from '../../dates/days.js';
import { stockAfter, usedOf } from '../../cooking/lines.js';
import { MEAL_ICONS } from '../../cooking/mealIcons.js';
import { MEAL_TYPES, mealTypeAt } from '../../cooking/mealType.js';
import BaseButton from '../ui/BaseButton.vue';
import BaseDatePicker from '../ui/BaseDatePicker.vue';
import BottomSheet from '../ui/BottomSheet.vue';
import ChipGroup from '../ui/ChipGroup.vue';
import FormField from '../ui/FormField.vue';
import CookSuccess from './CookSuccess.vue';
import UsedLines from './UsedLines.vue';

const props = defineProps({
    heading: { type: String, default: null },
    submitLabel: { type: String, default: null },
    title: { type: String, default: '' },
    lines: { type: Array, default: () => [] },
});
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['logged']);

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const session = useSession();
const { today } = useDates();

const type = ref('dinner');
const dayChoice = ref('today');
const otherDay = ref(null);
const eaterIds = ref([]);
const mealTitle = ref('');
const usedLines = ref([]);
const saving = ref(false);
const done = ref(null);

const members = session?.members ?? [];
const typeOptions = computed(() => MEAL_TYPES.map((value) => ({ value, label: t(`meals.type.${value}`), icon: MEAL_ICONS[value] })));
const dayOptions = computed(() => ['today', 'yesterday', 'other'].map((value) => ({ value, label: t(`meals.day.${value}`) })));
const eaterOptions = computed(() => members.map((member) => ({ value: member.id, label: member.name })));
const eatenOn = computed(() => {
    if (dayChoice.value === 'yesterday') return addDays(today.value, -1);
    return dayChoice.value === 'other' && otherDay.value ? otherDay.value : today.value;
});
const used = computed(() => usedOf(usedLines.value));
const canSubmit = computed(() => eaterIds.value.length > 0 && (mealTitle.value.trim() !== '' || used.value.length > 0));

watch(open, (isOpen) => {
    if (!isOpen) return;
    type.value = mealTypeAt();
    dayChoice.value = 'today';
    otherDay.value = null;
    eaterIds.value = members.map((member) => member.id);
    mealTitle.value = props.title;
    usedLines.value = props.lines.map((line) => ({ ...line }));
    done.value = null;
});

watch(dayChoice, (choice) => {
    if (choice === 'other' && otherDay.value === null) otherDay.value = today.value;
});

async function submit() {
    saving.value = true;
    try {
        const meal = await api.post('/api/meals', {
            title: mealTitle.value.trim() === '' ? null : mealTitle.value.trim(),
            type: type.value,
            eatenOn: eatenOn.value,
            eaterIds: eaterIds.value,
            used: used.value,
        });
        done.value = {
            headline: t('meals.done.title'),
            rows: usedLines.value.filter((line) => line.amount > 0).map((line) => ({ name: line.name, unit: line.unit, from: line.stock, to: stockAfter(line) })),
        };
        toast.success(t('meals.toast.logged'));
        emit('logged', meal);
    } catch (error) {
        toast.error(error.message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <BottomSheet v-model:open="open" :title="heading ?? t('meals.sheet.title')">
        <CookSuccess v-if="done" :rows="done.rows" :headline="done.headline" />
        <template v-else>
            <FormField :label="t('meals.type.label')" as="div">
                <ChipGroup v-model="type" :options="typeOptions" :label="t('meals.type.label')" />
            </FormField>
            <FormField :label="t('meals.day.label')" as="div">
                <ChipGroup v-model="dayChoice" :options="dayOptions" :label="t('meals.day.label')" />
                <BaseDatePicker v-if="dayChoice === 'other'" v-model="otherDay" />
            </FormField>
            <FormField :label="t('meals.eaters.label')" as="div">
                <ChipGroup v-model="eaterIds" multiple :options="eaterOptions" :label="t('meals.eaters.label')" />
            </FormField>
            <FormField :label="t('meals.title.label')" :hint="t('meals.title.hint')">
                <input v-model="mealTitle" type="text" maxlength="80" autocomplete="off" :placeholder="t('meals.title.placeholder')" />
            </FormField>
            <FormField :label="t('meals.lines.label')" as="div">
                <UsedLines v-model="usedLines" />
            </FormField>
        </template>
        <template #footer>
            <BaseButton v-if="done" @click="open = false">{{ t('meals.done.close') }}</BaseButton>
            <BaseButton v-else :loading="saving" :disabled="!canSubmit" data-test="submit-meal" @click="submit">{{ submitLabel ?? t('meals.sheet.submit') }}</BaseButton>
        </template>
    </BottomSheet>
</template>
