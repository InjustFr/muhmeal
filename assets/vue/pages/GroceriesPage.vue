<script setup>
import { computed, ref } from 'vue';
import { PhX } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import GroceryPrepare from '../components/groceries/GroceryPrepare.vue';
import GroceryShop from '../components/groceries/GroceryShop.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import PageHeader from '../components/ui/PageHeader.vue';
import { useApi } from '../composables/useApi.js';

const { t } = useI18n();
const api = useApi();

const list = ref(undefined);
const ingredients = ref([]);
api.load('/api/grocery-list', list);
api.load('/api/ingredients', ingredients);

const subtitle = computed(() => (list.value ? t('groceries.shop.startedBy', { name: list.value.startedBy.name }) : null));
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.groceries')" :subtitle="subtitle">
            <BaseButton to="/pantry" variant="ghost" data-test="close-groceries"><PhX size="1.125rem" weight="bold" aria-hidden="true" />{{ t('groceries.close') }}</BaseButton>
        </PageHeader>
        <GroceryShop v-if="list" :list="list" />
        <GroceryPrepare v-else-if="list === null" :ingredients="ingredients" />
    </div>
</template>
