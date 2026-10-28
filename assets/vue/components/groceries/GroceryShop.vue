<script setup>
import { computed } from 'vue';
import { PhBasket } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { useGroceryTicks } from '../../composables/useGroceryTicks.js';
import { useToast } from '../../composables/useToast.js';
import { currentLanguage } from '../../i18n/locale.js';
import { groupByLocation } from '../../groceries/lists.js';
import { describeQuantity } from '../../pantry/quantity.js';
import IngredientPicker from '../pantry/IngredientPicker.vue';
import BaseButton from '../ui/BaseButton.vue';
import EmptyState from '../ui/EmptyState.vue';
import PageSection from '../ui/PageSection.vue';
import TickRow from './TickRow.vue';

const props = defineProps({
    list: { type: Object, required: true },
});

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const ticks = useGroceryTicks();

const rows = computed(() => props.list.items.map(ticks.view));
const groups = computed(() => groupByLocation(rows.value));
const listed = computed(() => props.list.items.map((item) => item.ingredientId));
const done = computed(() => rows.value.filter((row) => row.ticked).length);

const detailOf = (row) => t('groceries.row.left', { amount: describeQuantity(row.stock, row.unit, currentLanguage(), t) });

const add = (ingredient) => toast.attempt(() => api.post('/api/grocery-list/items', { ingredientId: ingredient.id }));
</script>

<template>
    <div class="shop">
        <p class="shop__progress tabular" data-test="progress">{{ t('groceries.shop.progress', { done, total: rows.length }) }}</p>

        <IngredientPicker :exclude="listed" :label="t('groceries.shop.add')" :initial-stock="0" @pick="add" />

        <EmptyState v-if="rows.length === 0" :icon="PhBasket" :title="t('groceries.shop.empty.title')" :hint="t('groceries.shop.empty.hint')" />

        <PageSection v-for="group in groups" :key="group.location" :title="t(`pantry.location.${group.location}`)" :count="group.rows.length">
            <ul class="shop__list">
                <li v-for="row in group.rows" :key="row.id">
                    <TickRow
                        :model-value="row.ticked"
                        :label="row.name"
                        :detail="detailOf(row)"
                        :out="row.stock === 0"
                        :out-label="t('groceries.row.out')"
                        @update:model-value="(ticked) => ticks.tick(row, ticked)"
                    />
                </li>
            </ul>
        </PageSection>

        <BaseButton to="/groceries/put-away" class="shop__home" data-test="back-home">{{ t('groceries.shop.backHome') }}</BaseButton>
    </div>
</template>

<style scoped>
.shop { display: flex; flex-direction: column; gap: var(--space-5); }
.shop__progress { color: var(--color-muted); font-weight: 700; }
.shop__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.shop__home { align-self: stretch; }
</style>
