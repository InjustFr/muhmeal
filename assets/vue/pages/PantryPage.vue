<script setup>
import { computed, reactive, ref } from 'vue';
import { PhCarrot, PhMagnifyingGlass, PhPlus, PhShoppingCart } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import IngredientRow from '../components/pantry/IngredientRow.vue';
import IngredientSheet from '../components/pantry/IngredientSheet.vue';
import StockSheet from '../components/pantry/StockSheet.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ChipGroup from '../components/ui/ChipGroup.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { useStockAdjustments } from '../composables/useStockAdjustments.js';
import { LOCATION_ICONS, LOCATIONS } from '../pantry/locations.js';

const { t } = useI18n();
const api = useApi();
const adjustments = useStockAdjustments();

const ingredients = ref([]);
api.load('/api/ingredients', ingredients);

const place = ref('all');
const query = ref('');
const ingredientSheet = reactive({ open: false, ingredient: null, name: '' });
const stockSheet = reactive({ open: false, ingredient: null });

const placeOptions = computed(() => ['all', ...LOCATIONS].map((value) => ({ value, label: t(`pantry.location.${value}`), icon: LOCATION_ICONS[value] })));
const rows = computed(() => ingredients.value.map(adjustments.view));
const needle = computed(() => query.value.trim().toLowerCase());
const matches = computed(() => rows.value.filter((row) => (needle.value ? row.name.toLowerCase().includes(needle.value) : place.value === 'all' || row.location === place.value)));
const canAdd = computed(() => needle.value !== '' && !rows.value.some((row) => row.name.toLowerCase() === needle.value));
const inStockFirst = (list) => [...list.filter((row) => !row.out), ...list.filter((row) => row.out)];
const groups = computed(() => {
    const places = place.value === 'all' || needle.value !== '' ? LOCATIONS : [place.value];
    return places.map((location) => ({ location, rows: inStockFirst(matches.value.filter((row) => row.location === location)) })).filter((group) => group.rows.length > 0);
});

function addNamed() {
    Object.assign(ingredientSheet, { open: true, ingredient: null, name: query.value.trim() });
}

function edit(row) {
    Object.assign(ingredientSheet, { open: true, ingredient: row, name: '' });
}

function setStock(row) {
    Object.assign(stockSheet, { open: true, ingredient: row });
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.pantry')">
            <BaseButton to="/groceries" variant="secondary" data-test="go-shopping"><PhShoppingCart size="1.125rem" weight="bold" aria-hidden="true" />{{ t('pantry.groceries') }}</BaseButton>
        </PageHeader>

        <div class="pantry__tools">
            <ChipGroup v-model="place" variant="segmented" :options="placeOptions" :label="t('pantry.location.label')" />
            <div class="pantry__search">
                <PhMagnifyingGlass class="pantry__search-icon" size="1.25rem" weight="bold" aria-hidden="true" />
                <input v-model="query" class="control pantry__field" type="search" autocomplete="off" :aria-label="t('pantry.search.label')" :placeholder="t('pantry.search.placeholder')" @keydown.enter.prevent="canAdd && addNamed()" />
            </div>
            <BaseButton v-if="canAdd" variant="secondary" data-test="add-named" @click="addNamed"><PhPlus size="1.125rem" weight="bold" aria-hidden="true" />{{ t('pantry.search.add', { name: query.trim() }) }}</BaseButton>
        </div>

        <EmptyState v-if="rows.length === 0 && needle === ''" :icon="PhCarrot" :title="t('pantry.empty.title')" :hint="t('pantry.empty.hint')" />
        <EmptyState v-else-if="matches.length === 0 && !canAdd" :icon="PhCarrot" :title="t('pantry.noMatch.title')" :hint="t('pantry.noMatch.hint')" />

        <PageSection v-for="group in groups" :key="group.location" :title="t(`pantry.location.${group.location}`)" :count="group.rows.length">
            <ul class="pantry__list">
                <IngredientRow v-for="row in group.rows" :key="row.id" :ingredient="row" @edit="edit(row)" @stock="setStock(row)" @move="(to) => adjustments.move(row.id, row.stock, to)" />
            </ul>
        </PageSection>

        <IngredientSheet v-model:open="ingredientSheet.open" :ingredient="ingredientSheet.ingredient" :initial-name="ingredientSheet.name" :initial-location="place === 'all' ? 'pantry' : place" :ingredients="ingredients" />
        <StockSheet v-model:open="stockSheet.open" :ingredient="stockSheet.ingredient" />
    </div>
</template>

<style scoped>
.pantry__tools { display: flex; flex-direction: column; gap: var(--space-3); }
.pantry__search { position: relative; }
.pantry__search-icon { position: absolute; top: 50%; left: var(--space-4); color: var(--color-muted); pointer-events: none; transform: translateY(-50%); }
.pantry__field { padding-left: 2.75rem; }
.pantry__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
</style>
