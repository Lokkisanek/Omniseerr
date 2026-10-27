<script setup>
import { onMounted } from 'vue';
import { Pause, Play, RefreshCw, Trash2 } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useDownloadsStore } from '../../Stores/downloads';

const props = defineProps({
    initialQueue: { type: Array, default: () => [] },
});

const store = useDownloadsStore();

onMounted(() => {
    if (props.initialQueue?.length) {
        store.queue = props.initialQueue;
    }
});
</script>

<template>
    <AppLayout title="Activity">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Activity</h1>
                <p class="mt-1 text-sm text-slate-400">Unified Radarr / Sonarr / qBittorrent transfer desk.</p>
            </div>
            <button
                class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-slate-300 hover:bg-white/5"
                @click="store.refresh()"
            >
                <RefreshCw class="h-4 w-4" />
                Refresh
            </button>
        </div>

        <div class="space-y-3">
            <div
                v-for="item in store.queue"
                :key="item.hash"
                class="flex flex-col gap-4 rounded-2xl border border-white/10 bg-white/5 p-4 md:flex-row md:items-center"
            >
                <img
                    v-if="item.posterUrl"
                    :src="item.posterUrl"
                    class="h-20 w-14 rounded-lg object-cover"
                    alt=""
                />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-base font-medium text-white">
                        {{ item.cleanTitle }}
                        <span v-if="item.year" class="text-slate-400">({{ item.year }})</span>
                    </div>
                    <div class="mt-1 truncate font-mono text-xs text-slate-500">{{ item.rawName }}</div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-800">
                        <div class="h-full rounded-full bg-blue-500" :style="{ width: `${item.progress}%` }" />
                    </div>
                    <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-400">
                        <span>{{ item.progress }}%</span>
                        <span>{{ item.dlSpeedHuman }} ↓</span>
                        <span>{{ item.upSpeedHuman }} ↑</span>
                        <span>{{ item.sizeHuman }}</span>
                        <span>{{ item.seeds }}S / {{ item.peers }}L</span>
                        <span>{{ item.state }}</span>
                        <span>{{ item.arrStatus }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-lg bg-white/5 p-2 text-slate-300 hover:bg-white/10" @click="store.control(item.hash, 'pause')">
                        <Pause class="h-4 w-4" />
                    </button>
                    <button class="rounded-lg bg-white/5 p-2 text-slate-300 hover:bg-white/10" @click="store.control(item.hash, 'resume')">
                        <Play class="h-4 w-4" />
                    </button>
                    <button class="rounded-lg bg-white/5 p-2 text-slate-300 hover:bg-white/10" @click="store.control(item.hash, 'recheck')">
                        <RefreshCw class="h-4 w-4" />
                    </button>
                    <button class="rounded-lg bg-rose-500/10 p-2 text-rose-300 hover:bg-rose-500/20" @click="store.control(item.hash, 'delete')">
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div v-if="store.queue.length === 0" class="rounded-2xl border border-dashed border-white/10 p-12 text-center text-slate-400">
                Queue is empty.
            </div>
        </div>
    </AppLayout>
</template>
