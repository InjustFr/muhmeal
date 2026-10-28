<script setup>
import { ref } from 'vue';
import { PhBookOpenText, PhPlus } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import RecipeCard from '../components/cooking/RecipeCard.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { useApi } from '../composables/useApi.js';

const { t } = useI18n();
const api = useApi();

const recipes = ref([]);
api.load('/api/recipes', recipes);
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.recipes')">
            <BaseButton to="/recipes/new"><PhPlus size="1.125rem" weight="bold" aria-hidden="true" />{{ t('recipes.new') }}</BaseButton>
        </PageHeader>

        <EmptyState v-if="recipes.length === 0" :icon="PhBookOpenText" :title="t('recipes.empty.title')" :hint="t('recipes.empty.hint')" />
        <ul v-else class="recipes__list">
            <RecipeCard v-for="recipe in recipes" :key="recipe.id" :recipe="recipe" />
        </ul>
    </div>
</template>

<style scoped>
.recipes__list { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
</style>
