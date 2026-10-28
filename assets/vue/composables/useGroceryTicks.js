import { reactive } from 'vue';
import { useApi } from './useApi.js';
import { useToast } from './useToast.js';

export function useGroceryTicks() {
    const api = useApi();
    const toast = useToast();
    const pending = reactive({});

    const view = (item) => ({ ...item, ticked: pending[item.id]?.ticked ?? item.ticked });

    async function tick(item, ticked) {
        if (pending[item.id]) {
            pending[item.id].ticked = ticked;
            pending[item.id].sending += 1;
        } else {
            pending[item.id] = { ticked, sending: 1 };
        }
        try {
            await api.put(`/api/grocery-list/items/${item.id}/ticked`, { ticked });
        } catch (error) {
            toast.error(error.message);
            api.refreshAll();
        } finally {
            pending[item.id].sending -= 1;
            if (pending[item.id].sending === 0) delete pending[item.id];
        }
    }

    return { view, tick };
}
