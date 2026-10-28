<script setup>
import { PhCookingPot } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import EmptyState from '../components/ui/EmptyState.vue';
import PageHeader from '../components/ui/PageHeader.vue';

const { t } = useI18n();
</script>

<template>
    <div class="page">
        <PageHeader :title="t('nav.cook')" />
        <EmptyState :icon="PhCookingPot" :title="t('cook.empty.title')" :hint="t('cook.empty.hint')" />
    </div>
</template>
