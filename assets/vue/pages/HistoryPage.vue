<script setup>
import { PhClockCounterClockwise } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';

const { t } = useI18n();
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.history')" />
        <EmptyState :icon="PhClockCounterClockwise" :title="t('history.empty.title')" :hint="t('history.empty.hint')" />
    </div>
</template>
