<script setup>
import { useI18n } from 'vue-i18n';
import PageHeader from '../components/ui/PageHeader.vue';

const { t } = useI18n();

const CREDITS = [
    { name: 'Fredoka', author: 'The Fredoka Project Authors', license: 'SIL Open Font License 1.1', url: 'https://fonts.google.com/specimen/Fredoka' },
    { name: 'Nunito', author: 'The Nunito Project Authors', license: 'SIL Open Font License 1.1', url: 'https://fonts.google.com/specimen/Nunito' },
    { name: 'Phosphor Icons', author: 'Phosphor Icons', license: 'MIT', url: 'https://phosphoricons.com' },
    { name: 'Reka UI', author: 'UnoVue contributors', license: 'MIT', url: 'https://reka-ui.com' },
    { name: 'Vue', author: 'Evan You, Vue contributors', license: 'MIT', url: 'https://vuejs.org' },
    { name: 'Vue Router', author: 'Eduardo San Martin Morote, Vue Router contributors', license: 'MIT', url: 'https://router.vuejs.org' },
    { name: 'Vue I18n', author: 'kazuya kawaguchi, intlify contributors', license: 'MIT', url: 'https://vue-i18n.intlify.dev' },
];
</script>

<template>
    <div class="page">
        <PageHeader :title="t('credits.title')" :subtitle="t('credits.subtitle')" />
        <ul class="credits">
            <li v-for="credit in CREDITS" :key="credit.name" class="credits__item">
                <a :href="credit.url" rel="noopener" target="_blank">{{ credit.name }}</a>
                <span>{{ credit.author }} · {{ credit.license }}</span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.credits { display: flex; flex-direction: column; gap: var(--space-3); margin: 0; padding: 0; list-style: none; }
.credits__item { display: flex; flex-direction: column; }
.credits__item a { font-weight: 700; }
.credits__item span { color: var(--color-muted); font-size: var(--font-size-sm); }
</style>
