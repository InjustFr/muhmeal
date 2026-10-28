<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { PhCookingPot } from '@phosphor-icons/vue';
import { useI18n } from 'vue-i18n';
import { currentLanguage } from '../../i18n/locale.js';
import { ticks } from '../../cooking/lines.js';
import { describeQuantity } from '../../pantry/quantity.js';

const props = defineProps({
    rows: { type: Array, required: true },
    headline: { type: String, required: true },
});

const { t } = useI18n();

const STEPS = 10;
const STEP_EVERY = 70;

const shown = ref(props.rows.map((row) => row.from));
let timer = null;

onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        shown.value = props.rows.map((row) => row.to);
        return;
    }
    const frames = props.rows.map((row) => ticks(row.from, row.to, STEPS));
    let frame = 0;
    timer = window.setInterval(() => {
        frame += 1;
        shown.value = frames.map((values) => values[frame]);
        if (frame === STEPS) window.clearInterval(timer);
    }, STEP_EVERY);
});

onBeforeUnmount(() => window.clearInterval(timer));

const describe = (row, index) => describeQuantity(shown.value[index], row.unit, currentLanguage(), t);
</script>

<template>
    <div class="success" data-test="cook-success">
        <div class="success__stage" aria-hidden="true">
            <span class="success__puff success__puff--one" />
            <span class="success__puff success__puff--two" />
            <span class="success__puff success__puff--three" />
            <PhCookingPot class="success__pot" size="5rem" weight="duotone" />
        </div>
        <p class="success__headline">{{ headline }}</p>
        <ul v-if="rows.length > 0" class="success__rows">
            <li v-for="(row, index) in rows" :key="row.name" class="success__row" data-test="stock-tick">
                <span class="success__name">{{ row.name }}</span>
                <span class="success__stock tabular">{{ describe(row, index) }}</span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.success { display: flex; flex-direction: column; align-items: center; gap: var(--space-3); padding: var(--space-3) 0; }
.success__stage { position: relative; display: flex; align-items: flex-end; justify-content: center; width: 8rem; height: 8rem; }
.success__pot { color: var(--color-accent); transform-origin: 50% 100%; animation: pot-bounce 900ms cubic-bezier(0.28, 0.84, 0.42, 1) both; }

.success__puff { position: absolute; bottom: 4.5rem; width: 1.25rem; height: 1.25rem; border-radius: var(--radius-pill); background: var(--color-surface); box-shadow: 0 0 0 0.125rem var(--color-border); opacity: 0; animation: steam 1800ms ease-out infinite; }
.success__puff--one { left: 2.75rem; animation-delay: 300ms; }
.success__puff--two { left: 4rem; width: 1.5rem; height: 1.5rem; animation-delay: 750ms; }
.success__puff--three { left: 5.25rem; width: 1rem; height: 1rem; animation-delay: 1200ms; }

.success__headline { color: var(--color-ink); font-family: var(--font-display); font-size: 1.5rem; font-weight: 600; text-align: center; }
.success__rows { display: flex; flex-direction: column; gap: var(--space-1); width: 100%; margin: 0; padding: 0; list-style: none; }
.success__row { display: flex; align-items: baseline; justify-content: space-between; gap: var(--space-3); padding: var(--space-2) var(--space-3); border-radius: var(--radius-inner); background: var(--color-bg); }
.success__name { min-width: 0; color: var(--color-ink); font-weight: 700; overflow-wrap: anywhere; }
.success__stock { flex-shrink: 0; color: var(--color-accent-strong); font-family: var(--font-display); font-size: 1.125rem; font-weight: 600; }

@keyframes pot-bounce {
    0% { transform: translateY(-3rem) scale(0.9, 1.1); opacity: 0; }
    35% { transform: translateY(0) scale(1.18, 0.82); opacity: 1; }
    55% { transform: translateY(-1.25rem) scale(0.94, 1.06); }
    75% { transform: translateY(0) scale(1.08, 0.92); }
    100% { transform: translateY(0) scale(1); }
}

@keyframes steam {
    0% { transform: translateY(0) scale(0.6); opacity: 0; }
    25% { opacity: 0.9; }
    100% { transform: translateY(-3.25rem) scale(1.5); opacity: 0; }
}

@media (prefers-reduced-motion: reduce) {
    .success__pot { animation: none; }
    .success__puff { display: none; }
}
</style>
