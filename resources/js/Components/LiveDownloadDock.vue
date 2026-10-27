<script setup>
import { Pause, Play, Trash2 } from '@lucide/vue';
import { storeToRefs } from 'pinia';
import { computed } from 'vue';
import { useDownloadsStore } from '../Stores/downloads';

const store = useDownloadsStore();
const { queue, activeCount } = storeToRefs(store);

const visible = computed(() =>
    queue.value.filter((item) => (item.progress ?? 100) < 100).slice(0, 4),
);
</script>

<template>
    <div
        v-if="activeCount > 0"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-700 bg-gray-900/95 backdrop-blur lg:pl-64"
    >
        <div class="flex gap-3 overflow-x-auto px-4 py-3 sm:px-6 lg:px-8">
            <div
                v-for="item in visible"
                :key="item.hash"
                class="panel min-w-[280px] flex-1 p-3"
            >
                <div class="flex items-center gap-3">
                    <img
                        v-if="item.posterUrl"
                        :src="item.posterUrl"
                        class="h-12 w-8 rounded object-cover ring-1 ring-gray-700"
                        alt=""
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-white">{{ item.cleanTitle }}</div>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-gray-400">
                            <span>{{ item.progress }}% · {{ item.dlSpeedHuman }}</span>
                            <span class="uppercase">{{ item.state }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-700">
                            <div
                                class="h-full rounded-full bg-indigo-500 transition-all"
                                :style="{ width: `${item.progress}%` }"
                            />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button type="button" class="btn btn-default btn-sm !px-2" title="Pause" @click="store.control(item.hash, 'pause')">
                            <Pause class="h-3.5 w-3.5" />
                        </button>
                        <button type="button" class="btn btn-default btn-sm !px-2" title="Resume" @click="store.control(item.hash, 'resume')">
                            <Play class="h-3.5 w-3.5" />
                        </button>
                        <button type="button" class="btn btn-danger btn-sm !px-2" title="Delete" @click="store.control(item.hash, 'delete')">
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
