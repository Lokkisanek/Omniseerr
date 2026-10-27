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
        <div class="section">
            <div class="slider-header items-center justify-between">
                <div>
                    <h1 class="heading">Activity</h1>
                    <p class="description">Unified Radarr / Sonarr / qBittorrent transfer desk.</p>
                </div>
                <button type="button" class="btn btn-default" @click="store.refresh()">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Refresh
                </button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="item in store.queue"
                    :key="item.hash"
                    class="panel flex flex-col gap-4 p-4 md:flex-row md:items-center"
                >
                    <img
                        v-if="item.posterUrl"
                        :src="item.posterUrl"
                        class="h-20 w-14 rounded-lg object-cover shadow ring-1 ring-gray-700"
                        alt=""
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-base font-bold text-white">
                            {{ item.cleanTitle }}
                            <span v-if="item.year" class="font-medium text-gray-400">({{ item.year }})</span>
                        </div>
                        <div class="mt-1 truncate font-mono text-xs text-gray-500">{{ item.rawName }}</div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-700">
                            <div class="h-full rounded-full bg-indigo-500" :style="{ width: `${item.progress}%` }" />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-400">
                            <span>{{ item.progress }}%</span>
                            <span>{{ item.dlSpeedHuman }} ↓</span>
                            <span>{{ item.upSpeedHuman }} ↑</span>
                            <span>{{ item.sizeHuman }}</span>
                            <span>{{ item.seeds }}S / {{ item.peers }}L</span>
                            <span class="uppercase">{{ item.state }}</span>
                            <span>{{ item.arrStatus }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-default btn-sm" @click="store.control(item.hash, 'pause')">
                            <Pause class="h-4 w-4" />
                        </button>
                        <button type="button" class="btn btn-default btn-sm" @click="store.control(item.hash, 'resume')">
                            <Play class="h-4 w-4" />
                        </button>
                        <button type="button" class="btn btn-default btn-sm" @click="store.control(item.hash, 'recheck')">
                            <RefreshCw class="h-4 w-4" />
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" @click="store.control(item.hash, 'delete')">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    v-if="store.queue.length === 0"
                    class="panel border-dashed px-6 py-16 text-center text-sm text-gray-400"
                >
                    Queue is empty.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
