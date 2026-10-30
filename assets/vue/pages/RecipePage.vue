<script setup>
import { computed, reactive, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { PhArrowLeft, PhBookOpenText, PhCheckCircle, PhFlask, PhPencilSimple, PhWarning } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import MealSheet from '../components/cooking/MealSheet.vue';
import PrepSheet from '../components/cooking/PrepSheet.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import PageSection from '../components/ui/PageSection.vue';
import { useApi } from '../composables/useApi.js';
import { linesFromRecipe } from '../cooking/lines.js';
import { lacking } from '../cooking/recipe.js';
import { currentLanguage } from '../i18n/locale.js';
import { describeQuantity, formatQuantity } from '../pantry/quantity.js';

const { t } = useI18n();
const api = useApi();
const id = useRoute().params.id;

const recipes = ref([]);
const loaded = ref(false);
api.load('/api/recipes', recipes).catch(() => {}).finally(() => { loaded.value = true; });

const mealSheet = reactive({ open: false });
const prepSheet = reactive({ open: false, recipeId: null });

const recipe = computed(() => recipes.value.find((candidate) => candidate.id === id) ?? null);
const short = computed(() => (recipe.value ? lacking(recipe.value).map((line) => line.name).join(', ') : ''));
const prepFirst = computed(() => (recipe.value && recipe.value.missing.length === 0 ? recipe.value.preps : []));
const prep = computed(() => recipes.value.find((candidate) => candidate.id === prepSheet.recipeId) ?? null);
const waiting = (line) => !line.covered && !recipe.value.missing.includes(line.ingredientId);

function make(recipeId) {
    Object.assign(prepSheet, { open: true, recipeId });
}

const need = (line) => (line.amount === null ? t('recipes.page.some') : describeQuantity(line.amount, line.unit, currentLanguage(), t));
const have = (line) => formatQuantity(line.stock, currentLanguage());
</script>

<template>
    <div class="page">
        <EmptyState v-if="loaded && recipe === null" :icon="PhBookOpenText" :title="t('recipes.page.notFound.title')" :hint="t('recipes.page.notFound.hint')">
            <BaseButton to="/recipes" variant="secondary">{{ t('recipes.page.back') }}</BaseButton>
        </EmptyState>

        <template v-else-if="recipe">
            <RouterLink to="/recipes" class="recipe__back"><PhArrowLeft size="1rem" weight="bold" aria-hidden="true" />{{ t('recipes.page.back') }}</RouterLink>

            <PageHeader :title="recipe.name" :subtitle="recipe.makes === 'prep' ? t('recipes.page.makes', { amount: describeQuantity(recipe.output.yield, recipe.output.unit, currentLanguage(), t), name: recipe.output.name }) : null">
                <BaseButton :to="`/recipes/${recipe.id}/edit`" variant="secondary"><PhPencilSimple size="1.125rem" weight="bold" aria-hidden="true" />{{ t('recipes.page.edit') }}</BaseButton>
            </PageHeader>

            <p v-if="recipe.makes === 'prep'" class="recipe__badge"><PhFlask size="1rem" weight="bold" aria-hidden="true" />{{ t('recipes.badge.prep') }}</p>

            <PageSection :title="t('recipes.page.ingredients')" :count="recipe.lines.length">
                <ul class="recipe__lines">
                    <li v-for="line in recipe.lines" :key="line.ingredientId" :class="['recipe__line', { 'recipe__line--waiting': waiting(line), 'recipe__line--missing': !line.covered && !waiting(line) }]" data-test="recipe-line">
                        <component :is="line.covered ? PhCheckCircle : waiting(line) ? PhFlask : PhWarning" class="recipe__mark" size="1.5rem" weight="duotone" aria-hidden="true" />
                        <span class="recipe__name">{{ line.name }}<span v-if="line.manual" class="recipe__hand">{{ t('recipes.page.byHand') }}</span><span v-if="line.variant" class="recipe__hand" data-test="variant">{{ t('recipes.page.variant', { name: line.variant.name }) }}</span></span>
                        <span class="recipe__amounts tabular">
                            <span class="visually-hidden">{{ t('recipes.page.have') }}: </span><span data-test="have">{{ have(line) }}</span>
                            <span aria-hidden="true"> / </span>
                            <span class="visually-hidden">{{ t('recipes.page.need') }}: </span><span data-test="need">{{ need(line) }}</span>
                        </span>
                    </li>
                </ul>
            </PageSection>

            <div v-if="prepFirst.length > 0" class="recipe__short recipe__first" data-test="prep-first-note">
                <p>{{ t('recipes.page.prepFirst', { names: prepFirst.map((step) => step.name).join(', ') }) }}</p>
                <BaseButton variant="secondary" data-test="make-first" @click="make(prepFirst[0].id)"><PhFlask size="1.125rem" weight="bold" aria-hidden="true" />{{ t('cook.makeFirst', { name: prepFirst[0].name }) }}</BaseButton>
            </div>
            <p v-else-if="!recipe.makeable" class="recipe__short" data-test="short-note">{{ t('recipes.page.short', { names: short }) }}</p>

            <BaseButton v-if="recipe.makes === 'meal'" data-test="cook-it" @click="mealSheet.open = true">{{ t('recipes.page.cookIt') }}</BaseButton>
            <BaseButton v-else data-test="make-it" @click="make(recipe.id)">{{ t('recipes.page.makeIt') }}</BaseButton>

            <MealSheet v-model:open="mealSheet.open" :heading="t('meals.sheet.cookTitle', { name: recipe.name })" :submit-label="t('recipes.page.cookIt')" :title="recipe.name" :lines="linesFromRecipe(recipe)" />
            <PrepSheet v-model:open="prepSheet.open" :recipe="prep" />
        </template>
    </div>
</template>

<style scoped>
.recipe__back { display: inline-flex; align-self: flex-start; align-items: center; gap: var(--space-1); min-height: var(--target); color: var(--color-muted); font-weight: 700; text-decoration: none; }
.recipe__back:hover { color: var(--color-ink); text-decoration: underline; text-underline-offset: 0.1875rem; }
.recipe__badge { display: inline-flex; align-self: flex-start; align-items: center; gap: var(--space-1); padding: 0 var(--space-3); border-radius: var(--radius-pill); background: var(--color-highlight-soft); color: var(--color-ink); font-size: var(--font-size-xs); font-weight: 800; letter-spacing: var(--tracking-caps); text-transform: uppercase; }

.recipe__lines { display: flex; flex-direction: column; gap: var(--space-2); margin: 0; padding: 0; list-style: none; }
.recipe__line { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-3); border: 0.125rem solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); }
.recipe__line--waiting { border-color: var(--color-highlight); background: var(--color-highlight-soft); }
.recipe__line--waiting .recipe__mark { color: var(--color-ink); }
.recipe__line--missing { border-color: var(--color-danger); background: var(--color-danger-soft); }
.recipe__mark { flex-shrink: 0; color: var(--color-accent); }
.recipe__line--missing .recipe__mark { color: var(--color-danger); }
.recipe__name { display: flex; flex: 1; flex-direction: column; min-width: 0; color: var(--color-ink); font-weight: 800; overflow-wrap: anywhere; }
.recipe__hand { color: var(--color-subtle); font-size: var(--font-size-xs); font-weight: 700; }
.recipe__amounts { flex-shrink: 0; color: var(--color-ink); font-family: var(--font-display); font-weight: 600; }
.recipe__first { display: flex; flex-direction: column; align-items: flex-start; gap: var(--space-2); }
.recipe__short { padding: var(--space-3); border-radius: var(--radius); background: var(--color-highlight-soft); color: var(--color-ink); }
</style>
