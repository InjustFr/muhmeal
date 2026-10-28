import { onBeforeUnmount, onMounted } from 'vue';
import { useApi } from './useApi.js';

const POLL_EVERY = 60_000;

export function useSync() {
    const { refreshAll } = useApi();
    let timer = null;

    const refreshIfVisible = () => {
        if (document.visibilityState === 'visible') refreshAll();
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', refreshIfVisible);
        window.addEventListener('focus', refreshIfVisible);
        window.addEventListener('online', refreshIfVisible);
        timer = window.setInterval(refreshIfVisible, POLL_EVERY);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', refreshIfVisible);
        window.removeEventListener('focus', refreshIfVisible);
        window.removeEventListener('online', refreshIfVisible);
        window.clearInterval(timer);
    });
}
