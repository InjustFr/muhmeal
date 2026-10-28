<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { PhArrowLeft, PhBookOpenText, PhX } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import RecipeLineEditor from '../components/cooking/RecipeLineEditor.vue';
import IngredientPicker from '../components/pantry/IngredientPicker.vue';
import FractionChips from '../components/pantry/FractionChips.vue';
import QuantityStepper from '../components/pantry/QuantityStepper.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import ChipGroup from '../components/ui/ChipGroup.vue';
import ConfirmButton from '../components/ui/ConfirmButton.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import FieldError from '../components/ui/FieldError.vue';
import FormField from '../components/ui/FormField.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { useApi } from '../composables/useApi.js';
import { useToast } from '../composables/useToast.js';
import { formFromView, isComplete, KINDS, newLine, payloadOf } from '../cooking/recipe.js';
import { MAX_TWELFTHS, TWELFTHS_PER_UNIT } from '../pantry/quantity.js';

const { t } = useI18n();
const api = useApi();
const route = useRoute();
const router = useRouter();
const toast = useToast();

const recipes = ref([]);
const loaded = ref(false);
api.load('/api/recipes', recipes).catch(() => {}).finally(() => { loaded.value = true; });

const editingId = computed(() => (typeof route.params.id === 'string' ? route.params.id : null));
const existing = computed(() => recipes.value.find((candidate) => candidate.id === editingId.value) ?? null);
const missing = computed(() => editingId.value !== null && loaded.value && existing.value === null);

const form = reactive({ name: '', kind: 'meal', lines: [], output: null, whole: TWELFTHS_PER_UNIT, fraction: 0 });
const initialised = ref(false);
const saving = ref(false);
const problem = ref(null);

const kindOptions = computed(() => KINDS.map((value) => ({ value, label: t(`recipes.edit.kind.${value}`) })));
const used = computed(() => form.lines.map((line) => line.ingredientId));
const complete = computed(() => isComplete(form));

watch([existing, editingId], () => {
    if (initialised.value) return;
    if (editingId.value === null) {
        initialised.value = true;
    } else if (existing.value) {
        Object.assign(form, formFromView(existing.value));
        initialised.value = true;
    }
}, { immediate: true });

function addLine(ingredient) {
    if (!used.value.includes(ingredient.id)) form.lines.push(newLine(ingredient));
}

function chooseOutput(ingredient) {
    form.output = { id: ingredient.id, name: ingredient.name, unit: ingredient.unit };
}

async function save() {
    saving.value = true;
    problem.value = null;
    try {
        const body = payloadOf(form);
        const saved = editingId.value === null ? await api.post('/api/recipes', body) : await api.put(`/api/recipes/${editingId.value}`, body);
        toast.success(t('recipes.edit.saved', { name: saved.name }));
        router.replace(`/recipes/${saved.id}`);
    } catch (error) {
        if (error.status === 422) problem.value = error.fieldErrors.name ?? error.message;
        else toast.error(error.message);
    } finally {
        saving.value = false;
    }
}

async function remove() {
    const removed = await toast.attempt(() => api.del(`/api/recipes/${editingId.value}`), t('recipes.edit.deleted'));
    if (removed) router.replace('/recipes');
}
</script>

<template>
    <div class="page">
        <EmptyState v-if="missing" :icon="PhBookOpenText" :title="t('recipes.page.notFound.title')" :hint="t('recipes.page.notFound.hint')">
            <BaseButton to="/recipes" variant="secondary">{{ t('recipes.page.back') }}</BaseButton>
        </EmptyState>

        <template v-else-if="initialised">
            <RouterLink :to="editingId === null ? '/recipes' : `/recipes/${editingId}`" class="edit__back"><PhArrowLeft size="1rem" weight="bold" aria-hidden="true" />{{ editingId === null ? t('recipes.page.back') : form.name || t('recipes.page.back') }}</RouterLink>
            <PageHeader :title="editingId === null ? t('recipes.edit.titleNew') : t('recipes.edit.titleEdit')" />

            <form class="edit" @submit.prevent="save">
                <FormField :label="t('recipes.edit.name')">
                    <input v-model="form.name" type="text" maxlength="80" autocomplete="off" />
                </FormField>

                <FormField :label="t('recipes.edit.kind.label')" as="div">
                    <ChipGroup v-model="form.kind" variant="segmented" :options="kindOptions" :label="t('recipes.edit.kind.label')" />
                </FormField>

                <section class="edit__section" :aria-label="t('recipes.edit.lines')">
                    <h2 class="edit__heading">{{ t('recipes.edit.lines') }}</h2>
                    <p v-if="form.lines.length === 0" class="edit__empty">{{ t('recipes.edit.noLines') }}</p>
                    <ul v-else class="edit__lines">
                        <RecipeLineEditor v-for="(line, index) in form.lines" :key="line.ingredientId" v-model="form.lines[index]" @remove="form.lines.splice(index, 1)" />
                    </ul>
                    <IngredientPicker :exclude="used" @pick="addLine" />
                </section>

                <section v-if="form.kind === 'prep'" class="edit__section" :aria-label="t('recipes.edit.output')">
                    <h2 class="edit__heading">{{ t('recipes.edit.output') }}</h2>
                    <p class="edit__hint">{{ t('recipes.edit.outputHint') }}</p>
                    <div v-if="form.output" class="edit__chosen" data-test="prep-output">
                        <span class="edit__chosen-name">{{ form.output.name }}</span>
                        <button type="button" class="edit__clear" :aria-label="t('recipes.edit.clearOutput', { name: form.output.name })" @click="form.output = null"><PhX size="1.125rem" weight="bold" aria-hidden="true" /></button>
                    </div>
                    <IngredientPicker v-else :label="t('recipes.edit.pickOutput')" @pick="chooseOutput" />
                    <FormField :label="t('recipes.edit.yield')" as="div">
                        <QuantityStepper v-model="form.whole" :label="t('recipes.edit.yield')" :decrease-label="t('recipes.edit.yieldLess')" :increase-label="t('recipes.edit.yieldMore')" :step="TWELFTHS_PER_UNIT" :max="MAX_TWELFTHS - TWELFTHS_PER_UNIT" />
                        <FractionChips v-model="form.fraction" :label="t('recipes.edit.yieldFraction')" />
                    </FormField>
                </section>

                <FieldError v-if="problem">{{ problem }}</FieldError>

                <div class="edit__actions">
                    <span v-if="editingId !== null" class="edit__delete">
                        <ConfirmButton :label="t('recipes.edit.delete')" :title="t('recipes.edit.deleteTitle', { name: form.name })" :message="t('recipes.edit.deleteMessage')" variant="danger" @confirm="remove" />
                    </span>
                    <BaseButton type="submit" :loading="saving" :disabled="!complete" data-test="save-recipe">{{ t('recipes.edit.save') }}</BaseButton>
                </div>
            </form>
        </template>
    </div>
</template>

<style scoped>
.edit__back { display: inline-flex; align-self: flex-start; align-items: center; gap: var(--space-1); min-height: var(--target); color: var(--color-muted); font-weight: 700; text-decoration: none; }
.edit__back:hover { color: var(--color-ink); text-decoration: underline; text-underline-offset: 0.1875rem; }
.edit { display: flex; flex-direction: column; gap: var(--space-5); }
.edit__section { display: flex; flex-direction: column; gap: var(--space-3); }
.edit__heading { color: var(--color-muted); font-family: var(--font-body); font-size: var(--font-size-xs); font-weight: 800; letter-spacing: var(--tracking-caps); text-transform: uppercase; }
.edit__hint { color: var(--color-muted); font-size: var(--font-size-md); }
.edit__empty { padding: var(--space-3); border: 0.125rem dashed var(--color-border-strong); border-radius: var(--radius); color: var(--color-muted); text-align: center; }
.edit__lines { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.edit__chosen { display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); padding: var(--space-2) var(--space-3); border: 0.125rem solid var(--color-accent); border-radius: var(--radius); background: var(--color-accent-soft); }
.edit__chosen-name { color: var(--color-ink); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; overflow-wrap: anywhere; }
.edit__clear { display: inline-flex; align-items: center; justify-content: center; width: var(--target); height: var(--target); border: none; border-radius: var(--radius-pill); background: none; color: var(--color-muted); cursor: pointer; }
.edit__clear:hover { background: var(--color-hover); color: var(--color-ink); }
.edit__actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: var(--space-3); }
.edit__delete { margin-right: auto; }
</style>
