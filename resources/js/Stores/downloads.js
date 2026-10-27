import { defineStore } from 'pinia';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import '../bootstrap';

export const useDownloadsStore = defineStore('downloads', () => {
    const queue = ref([]);
    const connected = ref(false);
    let channel = null;
    let pollTimer = null;

    const activeCount = computed(() =>
        queue.value.filter((item) => (item.progress ?? 100) < 100).length,
    );

    async function markPresence(delta) {
        try {
            await axios.post('/api/telemetry/presence', { delta });
        } catch {
            // Presence is best-effort for the poller gate.
        }
    }

    async function refresh() {
        try {
            const { data } = await axios.get('/api/telemetry/snapshot');
            queue.value = data.queue ?? [];
        } catch {
            // Keep last known queue.
        }
    }

    async function control(hash, action) {
        await axios.post('/api/telemetry/control', { hash, action });
        await refresh();
    }

    function start() {
        markPresence(1);

        if (window.Echo) {
            channel = window.Echo.channel('downloads');
            channel.listen('.queue.updated', (payload) => {
                queue.value = payload.queue ?? [];
                connected.value = true;
            });
        }

        // Fallback poll if Reverb is unavailable.
        pollTimer = setInterval(() => {
            if (!connected.value) {
                refresh();
            }
        }, 4000);

        refresh();
    }

    function stop() {
        markPresence(-1);
        if (channel && window.Echo) {
            window.Echo.leave('downloads');
        }
        if (pollTimer) {
            clearInterval(pollTimer);
        }
    }

    return {
        queue,
        connected,
        activeCount,
        refresh,
        control,
        start,
        stop,
    };
});

// Tiny helper for components that prefer composition helpers.
export function useDownloadsLifecycle() {
    const store = useDownloadsStore();

    onMounted(() => store.start());
    onBeforeUnmount(() => store.stop());

    return store;
}
