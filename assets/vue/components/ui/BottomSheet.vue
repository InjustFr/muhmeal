<script setup>
import { PhX } from '@phosphor-icons/vue';
import { DialogClose, DialogContent, DialogOverlay, DialogPortal, DialogRoot, DialogTitle } from 'reka-ui';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    title: { type: String, required: true },
});
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['openAutoFocus', 'closeAutoFocus']);
</script>

<template>
    <DialogRoot v-model:open="open">
        <DialogPortal>
            <DialogOverlay class="sheet" />
            <DialogContent class="sheet__panel" :aria-describedby="undefined" @open-auto-focus="emit('openAutoFocus', $event)" @close-auto-focus="emit('closeAutoFocus', $event)">
                <span class="sheet__handle" aria-hidden="true" />
                <header class="sheet__header">
                    <DialogTitle class="sheet__title">{{ title }}</DialogTitle>
                    <DialogClose class="sheet__close" :aria-label="t('common.close')"><PhX size="1.25rem" weight="bold" aria-hidden="true" /></DialogClose>
                </header>
                <div class="sheet__body"><slot /></div>
                <footer v-if="$slots.footer" class="sheet__footer"><slot name="footer" /></footer>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

<style scoped>
.sheet { position: fixed; inset: 0; z-index: 50; background: var(--color-backdrop); animation: fade-in var(--transition); }

.sheet__panel {
    position: fixed;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 51;
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
    max-height: 92vh;
    padding: var(--space-2) var(--space-5) calc(var(--space-5) + env(safe-area-inset-bottom));
    border: 0.125rem solid var(--color-border);
    border-bottom: none;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    background: var(--color-surface);
    box-shadow: var(--shadow);
    overflow-y: auto;
    animation: sheet-up 260ms cubic-bezier(0.2, 0.9, 0.3, 1);
}

.sheet__handle { align-self: center; flex-shrink: 0; width: 3rem; height: 0.375rem; border-radius: var(--radius-pill); background: var(--color-border-strong); }
.sheet__header { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); }
.sheet__title { margin: 0; overflow-wrap: anywhere; font-family: var(--font-display); font-size: 1.375rem; font-weight: 600; line-height: 1.2; }
.sheet__close { display: inline-flex; flex-shrink: 0; align-items: center; justify-content: center; width: var(--target); height: var(--target); border: none; border-radius: var(--radius-pill); background: none; color: var(--color-muted); cursor: pointer; }
.sheet__close:hover { color: var(--color-ink); background: var(--color-hover); }
.sheet__body { display: flex; flex-direction: column; gap: var(--space-4); }
.sheet__footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: var(--space-3); }

@keyframes sheet-up { from { transform: translateY(100%); } }
@keyframes sheet-pop { from { opacity: 0; transform: translate(-50%, calc(-50% + 1rem)) scale(0.98); } }

@media (min-width: 48rem) {
    .sheet__panel {
        top: 50%;
        right: auto;
        bottom: auto;
        left: 50%;
        width: min(32rem, calc(100% - 2rem));
        max-height: calc(100vh - 2rem);
        padding: var(--space-5);
        border-bottom: 0.125rem solid var(--color-border);
        border-radius: var(--radius-lg);
        transform: translate(-50%, -50%);
        animation: sheet-pop var(--transition);
    }

    .sheet__handle { display: none; }
}
</style>
