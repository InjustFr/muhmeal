<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { PhCaretDown, PhCaretUp, PhShoppingBag } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import BoughtRow from '../components/groceries/BoughtRow.vue';
import TickRow from '../components/groceries/TickRow.vue';
import IngredientPicker from '../components/pantry/IngredientPicker.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { useToast } from '../composables/useToast.js';
import { usedOf } from '../cooking/lines.js';
import { boughtLine, groupByLocation } from '../groceries/lists.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const router = useRouter();

const list = ref(undefined);
api.load('/api/grocery-list', list);

const lines = ref([]);
const started = ref(false);
const showUnbought = ref(false);
const saving = ref(false);

watch(list, (current) => {
    if (current === null) {
        router.replace('/groceries');
    } else if (current && !started.value) {
        lines.value = current.items.filter((item) => item.ticked).map(boughtLine);
        started.value = true;
    }
}, { immediate: true });

const bought = computed(() => lines.value.map((line) => line.ingredientId));
const unbought = computed(() => (list.value?.items ?? []).filter((item) => !bought.value.includes(item.ingredientId)));
const groups = computed(() => groupByLocation(lines.value));
const known = computed(() => [...bought.value, ...unbought.value.map((item) => item.ingredientId)]);

function setAmount(ingredientId, amount) {
    lines.value = lines.value.map((line) => (line.ingredientId === ingredientId ? { ...line, amount } : line));
}

function include(source) {
    lines.value = [...lines.value, boughtLine(source)];
}

async function done() {
    saving.value = true;
    await toast.attempt(() => api.post('/api/grocery-list/put-away', { bought: usedOf(lines.value) }), t('groceries.putAway.toast'));
    saving.value = false;
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('groceries.putAway.title')">
            <BaseButton to="/groceries" variant="ghost">{{ t('groceries.putAway.back') }}</BaseButton>
        </PageHeader>

        <template v-if="started">
            <p class="put-away__hint">{{ t('groceries.putAway.hint') }}</p>

            <EmptyState v-if="groups.length === 0" :icon="PhShoppingBag" :title="t('groceries.putAway.nothing.title')" :hint="t('groceries.putAway.nothing.hint')" />

            <PageSection v-for="group in groups" :key="group.location" :title="t(`pantry.location.${group.location}`)" :count="group.rows.length">
                <ul class="put-away__list">
                    <BoughtRow v-for="line in group.rows" :key="line.ingredientId" :line="line" @change="(amount) => setAmount(line.ingredientId, amount)" />
                </ul>
            </PageSection>

            <section v-if="unbought.length > 0" class="put-away__unbought">
                <button type="button" class="put-away__toggle" :aria-expanded="showUnbought" data-test="toggle-unbought" @click="showUnbought = !showUnbought">
                    {{ showUnbought ? t('groceries.putAway.notBoughtHide') : t('groceries.putAway.notBoughtShow', { count: unbought.length }) }}
                    <component :is="showUnbought ? PhCaretUp : PhCaretDown" size="1rem" weight="bold" aria-hidden="true" />
                </button>
                <template v-if="showUnbought">
                    <p class="put-away__hint">{{ t('groceries.putAway.notBoughtHint') }}</p>
                    <ul class="put-away__list">
                        <li v-for="item in unbought" :key="item.id">
                            <TickRow :model-value="false" :label="item.name" :cross-out="false" @update:model-value="include(item)" />
                        </li>
                    </ul>
                </template>
            </section>

            <IngredientPicker :exclude="known" :label="t('groceries.putAway.add')" :initial-stock="0" @pick="include" />

            <BaseButton class="put-away__done" :loading="saving" data-test="put-away-done" @click="done">{{ t('groceries.putAway.done') }}</BaseButton>
        </template>
    </div>
</template>

<style scoped>
.put-away__hint { color: var(--color-muted); }
.put-away__list { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.put-away__unbought { display: flex; flex-direction: column; gap: var(--space-2); }
.put-away__toggle { display: inline-flex; align-self: flex-start; align-items: center; gap: var(--space-1); min-height: var(--target); padding: 0; border: none; background: none; color: var(--color-muted); font-weight: 700; cursor: pointer; }
.put-away__toggle:hover { color: var(--color-ink); }
.put-away__done { align-self: stretch; }
</style>
