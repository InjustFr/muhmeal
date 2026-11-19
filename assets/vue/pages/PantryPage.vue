<script setup>
import { computed, reactive, ref } from 'vue';
import { PhCarrot, PhMagnifyingGlass, PhPlus, PhShoppingCart } from '@phosphor-icons/vue';
import { RouterLink } from 'vue-router';
import { useI18n } from 'vue-i18n';
import ItemRow from '../components/pantry/ItemRow.vue';
import ItemSheet from '../components/pantry/ItemSheet.vue';
import StockSheet from '../components/pantry/StockSheet.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ChipGroup from '../components/ui/ChipGroup.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { useStockAdjustments } from '../composables/useStockAdjustments.js';
import { INGREDIENT_LOCATIONS, KIND_ICONS, KINDS, LOCATION_ICONS } from '../pantry/locations.js';
import { nameMatches } from '../pantry/search.js';

const { t } = useI18n();
const api = useApi();
const adjustments = useStockAdjustments();

const items = ref([]);
api.load('/api/items', items);

const kind = ref('ingredient');
const place = ref('all');
const query = ref('');
const itemSheet = reactive({ open: false, item: null, name: '' });
const stockSheet = reactive({ open: false, item: null });

const kindOptions = computed(() => KINDS.map((value) => ({ value, label: t(`pantry.kinds.${value}`), icon: KIND_ICONS[value] })));
const placeOptions = computed(() => ['all', ...INGREDIENT_LOCATIONS].map((value) => ({ value, label: t(`pantry.location.${value}`), icon: LOCATION_ICONS[value] })));
const rows = computed(() => items.value.map(adjustments.view));
const runningLow = computed(() => items.value.filter((item) => item.suggested).length);
const needle = computed(() => query.value.trim().toLowerCase());
const shown = (row) => row.kind === kind.value && (kind.value === 'household' || place.value === 'all' || row.location === place.value);
const matches = computed(() => rows.value.filter((row) => (needle.value ? nameMatches(row.name, needle.value) : shown(row))));
const canAdd = computed(() => needle.value !== '' && !rows.value.some((row) => row.name.toLowerCase() === needle.value));
const inStockFirst = (list) => [...list.filter((row) => !row.out), ...list.filter((row) => row.out)];
const groups = computed(() => {
    const places = place.value === 'all' || needle.value !== '' ? INGREDIENT_LOCATIONS : [place.value];
    const ingredients = matches.value.filter((row) => row.kind === 'ingredient');
    return [
        ...places.map((location) => ({ key: location, title: t(`pantry.location.${location}`), rows: inStockFirst(ingredients.filter((row) => row.location === location)) })),
        { key: 'household', title: t('pantry.kinds.household'), rows: inStockFirst(matches.value.filter((row) => row.kind === 'household')) },
    ].filter((group) => group.rows.length > 0);
});

function addNamed() {
    Object.assign(itemSheet, { open: true, item: null, name: query.value.trim() });
}

function edit(row) {
    Object.assign(itemSheet, { open: true, item: row, name: '' });
}

function setStock(row) {
    Object.assign(stockSheet, { open: true, item: row });
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.pantry')">
            <BaseButton to="/groceries" variant="secondary" data-test="go-shopping"><PhShoppingCart size="1.125rem" weight="bold" aria-hidden="true" />{{ t('pantry.groceries') }}</BaseButton>
        </PageHeader>

        <RouterLink v-if="runningLow > 0" to="/groceries" class="pantry__low" data-test="running-low"><PhShoppingCart size="1.25rem" weight="duotone" aria-hidden="true" />{{ t('pantry.low', { count: runningLow }, runningLow) }}</RouterLink>

        <div class="pantry__tools">
            <ChipGroup v-model="kind" variant="segmented" :options="kindOptions" :label="t('pantry.kinds.label')" />
            <ChipGroup v-if="kind === 'ingredient'" v-model="place" variant="segmented" :options="placeOptions" :label="t('pantry.location.label')" />
            <div class="pantry__search">
                <PhMagnifyingGlass class="pantry__search-icon" size="1.25rem" weight="bold" aria-hidden="true" />
                <input v-model="query" class="control pantry__field" type="search" autocomplete="off" :aria-label="t('pantry.search.label')" :placeholder="t('pantry.search.placeholder')" @keydown.enter.prevent="canAdd && addNamed()" />
            </div>
            <BaseButton v-if="canAdd" variant="secondary" data-test="add-named" @click="addNamed"><PhPlus size="1.125rem" weight="bold" aria-hidden="true" />{{ t('pantry.search.add', { name: query.trim() }) }}</BaseButton>
        </div>

        <EmptyState v-if="rows.length === 0 && needle === ''" :icon="PhCarrot" :title="t('pantry.empty.title')" :hint="t('pantry.empty.hint')" />
        <EmptyState v-else-if="matches.length === 0 && !canAdd" :icon="PhCarrot" :title="t('pantry.noMatch.title')" :hint="t('pantry.noMatch.hint')" />

        <PageSection v-for="group in groups" :key="group.key" :title="group.title" :count="group.rows.length">
            <ul class="pantry__list">
                <ItemRow v-for="row in group.rows" :key="row.id" :item="row" @edit="edit(row)" @stock="setStock(row)" @move="(to) => adjustments.move(row.id, row.stock, to)" />
            </ul>
        </PageSection>

        <ItemSheet v-model:open="itemSheet.open" :item="itemSheet.item" :initial-name="itemSheet.name" :initial-kind="kind" :initial-location="place === 'all' ? 'pantry' : place" :items="items" />
        <StockSheet v-model:open="stockSheet.open" :item="stockSheet.item" />
    </div>
</template>

<style scoped>
.pantry__low { display: inline-flex; align-self: flex-start; align-items: center; gap: var(--space-2); min-height: var(--target); padding: 0 var(--space-4); border: 0.125rem solid var(--color-highlight); border-radius: var(--radius-pill); background: var(--color-highlight-soft); color: var(--color-ink); font-weight: 800; text-decoration: none; }
.pantry__low:hover { background: var(--color-highlight); }
.pantry__tools { display: flex; flex-direction: column; gap: var(--space-3); }
.pantry__search { position: relative; }
.pantry__search-icon { position: absolute; top: 50%; left: var(--space-4); color: var(--color-muted); pointer-events: none; transform: translateY(-50%); }
.pantry__field { padding-left: 2.75rem; }
.pantry__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
</style>
