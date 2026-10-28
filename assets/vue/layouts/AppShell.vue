<script setup>
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { PhBookOpenText, PhCarrot, PhClockCounterClockwise, PhCookingPot, PhShoppingCart } from '@phosphor-icons/vue';
import { ConfigProvider, TooltipProvider } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import AccountMenu from '../components/nav/AccountMenu.vue';
import ToastHost from '../components/ui/ToastHost.vue';
import { useSync } from '../composables/useSync.js';
import { intlLocale } from '../i18n/locale.js';

const { t } = useI18n();
const route = useRoute();

useSync();

const TABS = [
    { to: '/', icon: PhCookingPot, label: 'nav.cook' },
    { to: '/pantry', icon: PhCarrot, label: 'nav.pantry' },
    { to: '/recipes', icon: PhBookOpenText, label: 'nav.recipes' },
    { to: '/groceries', icon: PhShoppingCart, label: 'nav.groceries' },
    { to: '/history', icon: PhClockCounterClockwise, label: 'nav.history' },
];

const tabActive = (tab) => (tab.to === '/' ? route.path === '/' : route.path === tab.to || route.path.startsWith(`${tab.to}/`));
</script>

<template>
    <ConfigProvider :locale="intlLocale()">
        <TooltipProvider :delay-duration="400">
            <div class="shell">
                <a class="shell__skip" href="#main">{{ t('nav.skip') }}</a>

                <header class="shell__header">
                    <RouterLink to="/" class="shell__brand"><PhCookingPot size="1.75rem" weight="fill" aria-hidden="true" />muhmeal</RouterLink>
                    <AccountMenu />
                </header>

                <nav class="shell__nav" :aria-label="t('nav.label')">
                    <RouterLink v-for="tab in TABS" :key="tab.to" :to="tab.to" :class="['shell__tab', { 'shell__tab--active': tabActive(tab) }]" active-class="" exact-active-class="">
                        <span class="shell__icon"><component :is="tab.icon" size="1.5rem" weight="duotone" aria-hidden="true" /></span>
                        <span class="shell__label">{{ t(tab.label) }}</span>
                    </RouterLink>
                </nav>

                <main id="main" class="shell__main">
                    <RouterView />
                </main>
            </div>

            <ToastHost />
        </TooltipProvider>
    </ConfigProvider>
</template>

<style scoped>
.shell { display: flex; flex-direction: column; min-height: 100vh; }

.shell__skip { position: absolute; top: -10rem; left: var(--space-4); z-index: 100; padding: var(--space-2) var(--space-4); border-radius: var(--radius); background: var(--color-ink); color: var(--color-surface); font-weight: 700; }
.shell__skip:focus { top: var(--space-4); }

.shell__header {
    position: sticky;
    top: 0;
    z-index: 30;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: var(--header-height);
    padding: 0 var(--space-4);
    border-bottom: 0.125rem solid var(--color-border);
    background: var(--color-surface);
}

.shell__brand { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-accent-strong); font-family: var(--font-display); font-size: 1.625rem; font-weight: 600; line-height: 1; text-decoration: none; }

.shell__nav {
    position: fixed;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 40;
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    padding: var(--space-1) var(--space-1) calc(var(--space-1) + env(safe-area-inset-bottom));
    border-top: 0.125rem solid var(--color-border);
    background: var(--color-surface);
}

.shell__tab { display: flex; flex-direction: column; align-items: center; gap: 0.125rem; min-height: var(--target); padding: var(--space-1) 0; color: var(--color-muted); font-size: var(--font-size-xs); font-weight: 700; text-decoration: none; }
.shell__icon { display: inline-flex; align-items: center; justify-content: center; width: 3.5rem; height: 2rem; border-radius: var(--radius-pill); transition: background var(--transition), color var(--transition); }
.shell__tab--active { color: var(--color-ink); }
.shell__tab--active .shell__icon { background: var(--color-accent-soft); color: var(--color-accent-strong); }

.shell__main { flex: 1; min-width: 0; padding: var(--space-5) var(--space-4) calc(6.5rem + env(safe-area-inset-bottom)); }
.shell__main :deep(.page) { display: flex; flex-direction: column; gap: var(--space-5); width: 100%; max-width: 46rem; }

@media (min-width: 48rem) {
    .shell { display: grid; grid-template-columns: var(--rail-width) minmax(0, 1fr); grid-template-rows: auto 1fr; grid-template-areas: "header header" "nav main"; }
    .shell__header { grid-area: header; padding: 0 var(--space-6); }

    .shell__nav {
        position: sticky;
        top: var(--header-height);
        grid-area: nav;
        align-self: start;
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
        height: calc(100vh - var(--header-height));
        padding: var(--space-4) var(--space-3);
        border-top: none;
        border-right: 0.125rem solid var(--color-border);
        background: var(--color-sidebar);
    }

    .shell__tab { flex-direction: row; gap: var(--space-3); padding: var(--space-2) var(--space-3); border-radius: var(--radius); font-size: var(--font-size); }
    .shell__tab:hover { background: var(--color-hover); color: var(--color-ink); }
    .shell__icon, .shell__tab--active .shell__icon { width: auto; height: auto; background: none; }
    .shell__tab--active, .shell__tab--active:hover { background: var(--color-accent-soft); }

    .shell__main { grid-area: main; padding: var(--space-6) var(--space-7) var(--space-7); }
}
</style>
