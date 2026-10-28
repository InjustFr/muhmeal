<script setup>
import { computed, ref } from 'vue';
import { PhCaretDown, PhCaretUp, PhClockCounterClockwise, PhTrash, PhUser } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import DeleteMealDialog from '../components/cooking/DeleteMealDialog.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import IconButton from '../components/ui/IconButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { useDates } from '../composables/useDates.js';
import { useToast } from '../composables/useToast.js';
import { MEAL_ICONS } from '../cooking/mealIcons.js';
import { currentLanguage } from '../i18n/locale.js';
import { describeQuantity } from '../pantry/quantity.js';

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const dates = useDates();

const page = ref({ meals: [], before: null });
api.load('/api/meals', page);

const older = ref([]);
const cursor = ref(undefined);
const loadingOlder = ref(false);
const expanded = ref(new Set());
const deleting = ref({ open: false, meal: null });

const nextCursor = computed(() => (cursor.value === undefined ? page.value.before : cursor.value));
const meals = computed(() => {
    const seen = new Set(page.value.meals.map((meal) => meal.id));

    return [...page.value.meals, ...older.value.filter((meal) => !seen.has(meal.id))];
});
const days = computed(() => {
    const byDay = new Map();
    meals.value.forEach((meal) => byDay.set(meal.eatenOn, [...(byDay.get(meal.eatenOn) ?? []), meal]));

    return [...byDay.entries()].map(([day, list]) => ({ day, meals: list }));
});

const dayLabel = (day) => {
    const relative = dates.relative(day);

    return relative === 'today' || relative === 'yesterday' ? t(`history.${relative}`) : dates.long(day);
};
const nameOf = (meal) => meal.title ?? meal.lines.map((line) => line.name).join(', ');
const quantityOf = (line) => describeQuantity(line.used, line.unit, currentLanguage(), t);

function toggle(id) {
    const next = new Set(expanded.value);
    if (!next.delete(id)) next.add(id);
    expanded.value = next;
}

async function loadOlder() {
    loadingOlder.value = true;
    try {
        const result = await api.get(`/api/meals?before=${nextCursor.value}`);
        older.value = [...older.value, ...result.meals];
        cursor.value = result.before;
    } catch (error) {
        toast.error(error.message);
    } finally {
        loadingOlder.value = false;
    }
}

function askToDelete(meal) {
    deleting.value = { open: true, meal };
}

async function remove(giveBack) {
    const meal = deleting.value.meal;
    const removed = await toast.attempt(() => api.del(`/api/meals/${meal.id}?giveBack=${giveBack ? 1 : 0}`), t('history.delete.done'));
    if (removed) older.value = older.value.filter((candidate) => candidate.id !== meal.id);
}
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.history')" />

        <EmptyState v-if="meals.length === 0" :icon="PhClockCounterClockwise" :title="t('history.empty.title')" :hint="t('history.empty.hint')" />

        <PageSection v-for="group in days" :key="group.day" :title="dayLabel(group.day)" :count="group.meals.length">
            <ul class="history__list">
                <li v-for="meal in group.meals" :key="meal.id" class="meal" data-test="meal">
                    <div class="meal__head">
                        <span class="meal__type"><component :is="MEAL_ICONS[meal.type]" size="1rem" weight="bold" aria-hidden="true" />{{ t(`meals.type.${meal.type}`) }}</span>
                        <IconButton :icon="PhTrash" :label="t('history.delete.label', { name: nameOf(meal) })" variant="danger" @click="askToDelete(meal)" />
                    </div>
                    <p class="meal__name">{{ nameOf(meal) }}</p>
                    <ul class="meal__eaters" :aria-label="t('history.eaters')">
                        <li v-for="eater in meal.eaters" :key="eater.id" class="meal__eater" data-test="eater"><PhUser size="0.875rem" weight="bold" aria-hidden="true" />{{ eater.name }}</li>
                    </ul>
                    <template v-if="meal.lines.length > 0">
                        <button type="button" class="meal__toggle" :aria-expanded="expanded.has(meal.id)" data-test="toggle-lines" @click="toggle(meal.id)">
                            {{ expanded.has(meal.id) ? t('history.lines.hide') : t('history.lines.show', { count: meal.lines.length }) }}
                            <component :is="expanded.has(meal.id) ? PhCaretUp : PhCaretDown" size="1rem" weight="bold" aria-hidden="true" />
                        </button>
                        <ul v-if="expanded.has(meal.id)" class="meal__lines">
                            <li v-for="(line, position) in meal.lines" :key="position" class="meal__line" data-test="meal-line"><span>{{ line.name }}</span><span class="tabular">{{ quantityOf(line) }}</span></li>
                        </ul>
                    </template>
                </li>
            </ul>
        </PageSection>

        <BaseButton v-if="nextCursor" variant="secondary" :loading="loadingOlder" data-test="load-older" @click="loadOlder">{{ t('history.loadOlder') }}</BaseButton>

        <DeleteMealDialog v-model:open="deleting.open" :name="deleting.meal ? nameOf(deleting.meal) : ''" :has-lines="(deleting.meal?.lines.length ?? 0) > 0" @confirm="remove" />
    </div>
</template>

<style scoped>
.history__list { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.meal { display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-3) var(--space-4); border: 0.125rem solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); box-shadow: var(--shadow); }
.meal__head { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); }
.meal__type { display: inline-flex; align-items: center; gap: var(--space-1); padding: 0 var(--space-3); border-radius: var(--radius-pill); background: var(--color-accent-soft); color: var(--color-accent-deep); font-size: var(--font-size-xs); font-weight: 800; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.meal__name { color: var(--color-ink); font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; overflow-wrap: anywhere; }
.meal__eaters { display: flex; flex-wrap: wrap; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.meal__eater { display: inline-flex; align-items: center; gap: var(--space-1); padding: 0 var(--space-2); border: 0.125rem solid var(--color-border-strong); border-radius: var(--radius-pill); font-size: var(--font-size-sm); font-weight: 700; }
.meal__toggle { display: inline-flex; align-self: flex-start; align-items: center; gap: var(--space-1); min-height: var(--target); padding: 0; border: none; background: none; color: var(--color-muted); font-weight: 700; cursor: pointer; }
.meal__toggle:hover { color: var(--color-ink); }
.meal__lines { display: flex; flex-direction: column; gap: var(--space-1); margin: 0; padding: 0; list-style: none; }
.meal__line { display: flex; justify-content: space-between; gap: var(--space-3); padding: var(--space-1) var(--space-3); border-radius: var(--radius-inner); background: var(--color-bg); }
</style>
