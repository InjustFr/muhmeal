<script setup>
import { PhShoppingCart } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';

const { t } = useI18n();
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.groceries')" />
        <EmptyState :icon="PhShoppingCart" :title="t('groceries.empty.title')" :hint="t('groceries.empty.hint')" />
    </div>
</template>
