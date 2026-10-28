<script setup>
import { ref, watch } from 'vue';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
} from 'reka-ui';
import { useI18n } from 'vue-i18n';
import BaseButton from '../ui/BaseButton.vue';
import BaseSwitch from '../ui/BaseSwitch.vue';

defineProps({
    name: { type: String, default: '' },
    hasLines: { type: Boolean, default: false },
});
const open = defineModel('open', { type: Boolean, required: true });
const emit = defineEmits(['confirm']);

const { t } = useI18n();

const giveBack = ref(true);

watch(open, (isOpen) => {
    if (isOpen) giveBack.value = true;
});
</script>

<template>
    <AlertDialogRoot v-model:open="open">
        <AlertDialogPortal>
            <AlertDialogOverlay class="modal">
                <AlertDialogContent class="modal__panel">
                    <AlertDialogTitle class="modal__title">{{ t('history.delete.title') }}</AlertDialogTitle>
                    <AlertDialogDescription class="delete__message">{{ t('history.delete.message', { name }) }}</AlertDialogDescription>
                    <div v-if="hasLines" class="delete__back">
                        <span class="delete__label">
                            <span class="delete__title">{{ t('history.delete.putBack') }}</span>
                            <span class="delete__hint">{{ t('history.delete.putBackHint') }}</span>
                        </span>
                        <BaseSwitch v-model="giveBack" :label="t('history.delete.putBack')" />
                    </div>
                    <div class="actions-row">
                        <AlertDialogCancel as-child><BaseButton variant="ghost">{{ t('common.cancel') }}</BaseButton></AlertDialogCancel>
                        <AlertDialogAction as-child><BaseButton variant="danger" data-test="confirm-delete-meal" @click="emit('confirm', hasLines && giveBack)">{{ t('history.delete.confirm') }}</BaseButton></AlertDialogAction>
                    </div>
                </AlertDialogContent>
            </AlertDialogOverlay>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>

<style scoped>
.delete__message { margin: 0; color: var(--color-muted); overflow-wrap: anywhere; }
.delete__back { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-3); border-radius: var(--radius); background: var(--color-bg); }
.delete__label { display: flex; flex-direction: column; }
.delete__title { color: var(--color-ink); font-weight: 800; }
.delete__hint { color: var(--color-muted); font-size: var(--font-size-sm); }
</style>
