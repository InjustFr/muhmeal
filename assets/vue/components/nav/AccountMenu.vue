<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { PhArrowSquareOut, PhHeart, PhSignOut } from '@phosphor-icons/vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuSeparator, DropdownMenuTrigger } from 'reka-ui';
import { useI18n } from 'vue-i18n';
import { useSession } from '../../composables/useSession.js';

const { t } = useI18n();
const session = useSession();
const logout = ref(null);

const initial = computed(() => (Array.from(session.displayName)[0] ?? '').toUpperCase());
</script>

<template>
    <div class="account-menu">
        <DropdownMenuRoot>
            <DropdownMenuTrigger class="account-menu__trigger" :aria-label="t('nav.account')" data-test="account-menu">{{ initial }}</DropdownMenuTrigger>
            <DropdownMenuPortal>
                <DropdownMenuContent class="popover account-menu__content" align="end" :side-offset="8">
                    <DropdownMenuLabel class="account-menu__identity">
                        <span class="account-menu__name">{{ session.displayName }}</span>
                        <span class="account-menu__email">{{ session.email }}</span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator class="popover__separator" />
                    <DropdownMenuItem as-child>
                        <RouterLink to="/credits" class="popover__item"><PhHeart size="1.25rem" weight="duotone" aria-hidden="true" />{{ t('nav.credits') }}</RouterLink>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="session.accountsUrl" as-child>
                        <a :href="session.accountsUrl" class="popover__item"><PhArrowSquareOut size="1.25rem" weight="duotone" aria-hidden="true" />{{ t('nav.manageAccount') }}</a>
                    </DropdownMenuItem>
                    <DropdownMenuItem class="popover__item" @select="logout.requestSubmit()">
                        <PhSignOut size="1.25rem" weight="duotone" aria-hidden="true" />{{ t('nav.signOut') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenuPortal>
        </DropdownMenuRoot>
        <form ref="logout" method="post" action="/logout" hidden>
            <input type="hidden" name="_csrf_token" :value="session.logoutToken">
        </form>
    </div>
</template>

<style scoped>
.account-menu__trigger { display: inline-flex; align-items: center; justify-content: center; width: var(--target); height: var(--target); border: 0.125rem solid var(--color-accent-deep); border-radius: var(--radius-pill); background: var(--color-highlight); color: var(--color-on-highlight); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; cursor: pointer; }
.account-menu__content { min-width: 15rem; }
.account-menu__identity { display: flex; flex-direction: column; padding: var(--space-2) var(--space-3); }
.account-menu__name { color: var(--color-ink); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; overflow-wrap: anywhere; }
.account-menu__email { color: var(--color-muted); font-size: var(--font-size-sm); overflow-wrap: anywhere; }
</style>
