<script setup>
import { computed } from 'vue';
import { currentLanguage } from '../../i18n/locale.js';
import { formatQuantity, FRACTIONS } from '../../pantry/quantity.js';
import ChipGroup from '../ui/ChipGroup.vue';

defineProps({
    label: { type: String, required: true },
});
const model = defineModel({ type: Number, required: true });

const options = computed(() => FRACTIONS.map((twelfths) => ({ value: twelfths, label: formatQuantity(twelfths, currentLanguage()) })));
</script>

<template>
    <ChipGroup v-model="model" :options="options" :label="label" />
</template>
