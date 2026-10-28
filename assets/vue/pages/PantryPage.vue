<script setup>
import { PhCarrot } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';

const { t } = useI18n();
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.pantry')" />
        <EmptyState :icon="PhCarrot" :title="t('pantry.empty.title')" :hint="t('pantry.empty.hint')" />
    </div>
</template>
