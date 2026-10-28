<script setup>
import { PhBookOpenText } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';

const { t } = useI18n();
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.recipes')" />
        <EmptyState :icon="PhBookOpenText" :title="t('recipes.empty.title')" :hint="t('recipes.empty.hint')" />
    </div>
</template>
