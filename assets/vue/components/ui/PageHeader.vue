<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">{{ title }}</h1>
            <p v-if="subtitle" class="page-header__subtitle">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.default" class="page-header__actions"><slot /></div>
    </header>
</template>

<style scoped>
.page-header { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: var(--space-3); padding-bottom: var(--space-4); border-bottom: 0.125rem solid var(--color-border); }
.page-header__text { display: flex; flex-direction: column; gap: var(--space-1); min-width: 0; }
.page-header__title { overflow-wrap: anywhere; }
.page-header__subtitle { color: var(--color-muted); }
.page-header__actions { display: flex; align-items: center; gap: var(--space-2); }
</style>
