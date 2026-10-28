<script setup>
import { computed, ref } from 'vue';
import { PhBasket } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage } from '../../i18n/locale.js';
import { prepareRows } from '../../groceries/lists.js';
import { describeQuantity } from '../../pantry/quantity.js';
import IngredientPicker from '../pantry/IngredientPicker.vue';
import BaseButton from '../ui/BaseButton.vue';
import EmptyState from '../ui/EmptyState.vue';
import PageSection from '../ui/PageSection.vue';
import TickRow from './TickRow.vue';

const props = defineProps({
    ingredients: { type: Array, required: true },
});

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const extraIds = ref([]);
const skipped = ref([]);
const starting = ref(false);

const rows = computed(() => prepareRows(props.ingredients, extraIds.value));
const chosen = computed(() => rows.value.filter((row) => !skipped.value.includes(row.id)));
const listed = computed(() => rows.value.map((row) => row.id));

const detailOf = (row) => t('groceries.row.left', { amount: describeQuantity(row.stock, row.unit, currentLanguage(), t) });

function choose(row, ticked) {
    skipped.value = ticked ? skipped.value.filter((id) => id !== row.id) : [...skipped.value, row.id];
}

function add(ingredient) {
    extraIds.value = [...extraIds.value, ingredient.id];
}

async function start() {
    starting.value = true;
    await toast.attempt(() => api.post('/api/grocery-list', { ingredientIds: chosen.value.map((row) => row.id) }), t('groceries.prepare.starting'));
    starting.value = false;
}
</script>

<template>
    <div class="prepare">
        <p class="prepare__hint">{{ t('groceries.prepare.hint') }}</p>

        <PageSection v-if="rows.length > 0" :title="t('groceries.prepare.section')" :count="chosen.length">
            <ul class="prepare__list">
                <li v-for="row in rows" :key="row.id">
                    <TickRow
                        :model-value="!skipped.includes(row.id)"
                        :label="row.name"
                        :detail="detailOf(row)"
                        :out="row.stock === 0"
                        :out-label="t('groceries.row.out')"
                        :cross-out="false"
                        @update:model-value="(ticked) => choose(row, ticked)"
                    />
                </li>
            </ul>
        </PageSection>
        <EmptyState v-else :icon="PhBasket" :title="t('groceries.prepare.empty.title')" :hint="t('groceries.prepare.empty.hint')" />

        <IngredientPicker :exclude="listed" :label="t('groceries.prepare.add')" :initial-stock="0" @pick="add" />

        <BaseButton class="prepare__start" :loading="starting" :disabled="chosen.length === 0" data-test="start-shopping" @click="start">{{ t('groceries.prepare.start') }}</BaseButton>
    </div>
</template>

<style scoped>
.prepare { display: flex; flex-direction: column; gap: var(--space-5); }
.prepare__hint { color: var(--color-muted); }
.prepare__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.prepare__start { align-self: stretch; }
</style>
