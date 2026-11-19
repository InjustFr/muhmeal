<script setup>
import { computed, nextTick, ref } from 'vue';
import { PhMagnifyingGlass, PhPlus, PhX } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '../../composables/useApi.js';
import { currentLanguage } from '../../i18n/locale.js';
import { LOCATION_ICONS } from '../../pantry/locations.js';
import { describeQuantity } from '../../pantry/quantity.js';
import { nameMatches } from '../../pantry/search.js';
import BaseButton from '../ui/BaseButton.vue';
import ItemSheet from './ItemSheet.vue';

const props = defineProps({
    exclude: { type: Array, default: () => [] },
    label: { type: String, default: null },
    initialStock: { type: Number, default: undefined },
    ingredientsOnly: { type: Boolean, default: false },
});
const emit = defineEmits(['pick']);

const { t } = useI18n();
const api = useApi();

const items = ref([]);
api.load('/api/items', items);

const expanded = ref(false);
const query = ref('');
const search = ref(null);
const sheet = ref({ open: false, name: '' });

const words = computed(() => (props.ingredientsOnly ? 'pantry.picker.ingredients' : 'pantry.picker.items'));
const needle = computed(() => query.value.trim().toLowerCase());
const candidates = computed(() => items.value.filter((item) => !props.exclude.includes(item.id) && !(props.ingredientsOnly && item.kind === 'household')));
const matches = computed(() => candidates.value.filter((item) => nameMatches(item.name, needle.value)));
const canCreate = computed(() => needle.value !== '' && !items.value.some((item) => item.name.toLowerCase() === needle.value));

async function expand() {
    expanded.value = true;
    await nextTick();
    search.value?.focus();
}

function collapse() {
    expanded.value = false;
    query.value = '';
}

function pick(item) {
    emit('pick', item);
    collapse();
}

function create() {
    sheet.value = { open: true, name: query.value.trim() };
}

function enter() {
    if (matches.value.length > 0) pick(matches.value[0]);
    else if (canCreate.value) create();
}

const quantityOf = (item) => describeQuantity(item.stock, item.unit, currentLanguage(), t);
</script>

<template>
    <div class="picker">
        <BaseButton v-if="!expanded" variant="secondary" data-test="picker-open" @click="expand"><PhPlus size="1.125rem" weight="bold" aria-hidden="true" />{{ label ?? t(`${words}.add`) }}</BaseButton>
        <div v-else class="picker__panel">
            <div class="picker__search">
                <PhMagnifyingGlass class="picker__icon" size="1.25rem" weight="bold" aria-hidden="true" />
                <input ref="search" v-model="query" class="control picker__field" type="search" autocomplete="off" :aria-label="t(`${words}.search`)" :placeholder="t(`${words}.search`)" @keydown.enter.prevent="enter" />
                <button type="button" class="picker__close" :aria-label="t('pantry.picker.close')" @click="collapse"><PhX size="1.125rem" weight="bold" aria-hidden="true" /></button>
            </div>
            <ul class="picker__list">
                <li v-for="item in matches" :key="item.id">
                    <button type="button" class="picker__option" data-test="picker-option" @click="pick(item)">
                        <span :class="['picker__place', `picker__place--${item.location}`]" aria-hidden="true"><component :is="LOCATION_ICONS[item.location]" size="1rem" weight="duotone" /></span>
                        <span class="picker__name">{{ item.name }}</span>
                        <span class="picker__stock tabular">{{ t('pantry.picker.left', { amount: quantityOf(item) }) }}</span>
                    </button>
                </li>
                <li v-if="matches.length === 0 && !canCreate" class="picker__none">{{ t(`${words}.none`) }}</li>
                <li v-if="canCreate">
                    <button type="button" class="picker__option picker__option--create" data-test="picker-create" @click="create"><PhPlus size="1.125rem" weight="bold" aria-hidden="true" />{{ t('pantry.picker.create', { name: query.trim() }) }}</button>
                </li>
            </ul>
        </div>
        <ItemSheet v-model:open="sheet.open" :initial-name="sheet.name" :initial-stock="initialStock" :allow-another="false" :ingredients-only="ingredientsOnly" @saved="pick" />
    </div>
</template>

<style scoped>
.picker { display: flex; flex-direction: column; gap: var(--space-2); }
.picker__panel { display: flex; flex-direction: column; gap: var(--space-2); padding: var(--space-3); border: 0.125rem solid var(--color-border); border-radius: var(--radius); background: var(--color-bg); }
.picker__search { position: relative; }
.picker__icon { position: absolute; top: 50%; left: var(--space-4); color: var(--color-muted); pointer-events: none; transform: translateY(-50%); }
.picker__field { padding-right: 2.75rem; padding-left: 2.75rem; }
.picker__close { position: absolute; top: 50%; right: var(--space-1); display: inline-flex; align-items: center; justify-content: center; width: var(--target); height: var(--target); border: none; border-radius: var(--radius-pill); background: none; color: var(--color-muted); cursor: pointer; transform: translateY(-50%); }
.picker__close:hover { background: var(--color-hover); color: var(--color-ink); }

.picker__list { display: flex; flex-direction: column; gap: var(--space-1); max-height: 14rem; margin: 0; padding: 0; overflow-y: auto; list-style: none; }
.picker__option { display: flex; align-items: center; gap: var(--space-3); width: 100%; min-height: var(--target); padding: var(--space-1) var(--space-3); border: 0.125rem solid transparent; border-radius: var(--radius-inner); background: var(--color-surface); color: var(--color-ink); font-weight: 700; text-align: left; cursor: pointer; }
.picker__option:hover { background: var(--color-accent-soft); }
.picker__option--create { color: var(--color-accent-strong); }
.picker__place { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: 1.75rem; height: 1.75rem; border-radius: var(--radius-pill); }
.picker__place--fridge { background: var(--location-fridge); }
.picker__place--freezer { background: var(--location-freezer); }
.picker__place--pantry { background: var(--location-pantry); }
.picker__place--home { background: var(--location-home); }
.picker__name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.picker__stock { flex-shrink: 0; color: var(--color-muted); font-size: var(--font-size-sm); font-weight: 600; }
.picker__none { padding: var(--space-2) var(--space-3); color: var(--color-muted); }
</style>
