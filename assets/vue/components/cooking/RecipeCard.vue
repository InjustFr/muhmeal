<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { PhCheckCircle, PhFlask, PhWarning } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({
    recipe: { type: Object, required: true },
    actionLabel: { type: String, default: null },
});
const emit = defineEmits(['act']);

const { t } = useI18n();

const SHOWN = 4;

const summary = computed(() => {
    const names = props.recipe.lines.map((line) => line.name);
    const more = names.length - SHOWN;

    return more > 0 ? `${names.slice(0, SHOWN).join(', ')} ${t('recipes.card.more', { count: more })}` : names.join(', ');
});
</script>

<template>
    <li class="card" data-test="recipe-card">
        <div class="card__text">
            <RouterLink :to="`/recipes/${recipe.id}`" class="card__name">{{ recipe.name }}</RouterLink>
            <p class="card__summary">{{ summary }}</p>
            <div class="card__chips">
                <span v-if="recipe.makes === 'prep'" class="card__chip card__chip--prep"><PhFlask size="0.875rem" weight="bold" aria-hidden="true" />{{ t('recipes.badge.prep') }}</span>
                <span v-if="recipe.makeable" class="card__chip card__chip--ready"><PhCheckCircle size="0.875rem" weight="bold" aria-hidden="true" />{{ t('recipes.status.ready') }}</span>
                <span v-else-if="recipe.missing.length === 0" class="card__chip card__chip--later" data-test="prep-first"><PhFlask size="0.875rem" weight="bold" aria-hidden="true" />{{ t('recipes.status.prepFirst', { names: recipe.preps.map((prep) => prep.name).join(', ') }) }}</span>
                <span v-else class="card__chip card__chip--missing"><PhWarning size="0.875rem" weight="bold" aria-hidden="true" />{{ t('recipes.status.missing', { count: recipe.missing.length }, recipe.missing.length) }}</span>
            </div>
        </div>
        <BaseButton v-if="actionLabel" class="card__action" :aria-label="`${actionLabel}: ${recipe.name}`" data-test="recipe-action" @click="emit('act')">{{ actionLabel }}</BaseButton>
    </li>
</template>

<style scoped>
.card { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-3) var(--space-4); border: 0.125rem solid var(--color-border); border-radius: var(--radius-lg); background: var(--color-surface); box-shadow: var(--shadow); }
.card__action { flex-shrink: 0; white-space: nowrap; }
.card__text { display: flex; flex: 1 1 10rem; flex-direction: column; gap: var(--space-1); min-width: 0; }
.card__name { color: var(--color-ink); font-family: var(--font-display); font-size: 1.25rem; font-weight: 600; text-decoration: none; overflow-wrap: break-word; }
.card__name:hover { text-decoration: underline; text-underline-offset: 0.1875rem; }
.card__summary { color: var(--color-muted); font-size: var(--font-size-md); overflow-wrap: anywhere; }
.card__chips { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.card__chip { display: inline-flex; align-items: center; gap: var(--space-1); padding: 0 var(--space-2); border-radius: var(--radius-pill); font-size: var(--font-size-xs); font-weight: 800; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.card__chip--prep { background: var(--color-highlight-soft); color: var(--color-ink); }
.card__chip--ready { background: var(--color-accent-soft); color: var(--color-accent-deep); }
.card__chip--later { min-width: 0; background: var(--color-highlight-soft); color: var(--color-ink); letter-spacing: normal; text-transform: none; overflow-wrap: anywhere; }
.card__chip--missing { background: var(--color-danger-soft); color: var(--color-danger); }
</style>
