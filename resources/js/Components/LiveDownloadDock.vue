<script setup>
import { Pause, Play, Trash2 } from '@lucide/vue';
import { storeToRefs } from 'pinia';
import { useDownloadsStore } from '../Stores/downloads';

const store = useDownloadsStore();
const { queue, activeCount } = storeToRefs(store);

const visible = () => queue.value.filter((item) => (item.progress ?? 100) < 100).slice(0, 4);
</script>

<template>
    <div
        v-if="activeCount > 0"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-white/10 bg-slate-950/85 backdrop-blur-xl"
    >
        <div class="mx-auto flex max-w-7xl gap-3 overflow-x-auto px-4 py-3 sm:px-6">
            <div
                v-for="item in visible()"
                :key="item.hash"
                class="min-w-[280px] flex-1 rounded-xl border border-white/10 bg-white/5 p-3"
            >
                <div class="flex items-center gap-3">
                    <img
                        v-if="item.posterUrl"
                        :src="item.posterUrl"
                        class="h-12 w-8 rounded object-cover"
                        alt=""
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-white">{{ item.cleanTitle }}</div>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ item.progress }}% · {{ item.dlSpeedHuman }}</span>
                            <span>{{ item.state }}</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: `${item.progress}%` }" />
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <button
                            class="rounded-md bg-white/5 p-1.5 text-slate-300 hover:bg-white/10"
                            title="Pause"
                            @click="store.control(item.hash, 'pause')"
                        >
                            <Pause class="h-3.5 w-3.5" />
                        </button>
                        <button
                            class="rounded-md bg-white/5 p-1.5 text-slate-300 hover:bg-white/10"
                            title="Resume"
                            @click="store.control(item.hash, 'resume')"
                        >
                            <Play class="h-3.5 w-3.5" />
                        </button>
                        <button
                            class="rounded-md bg-white/5 p-1.5 text-rose-300 hover:bg-rose-500/20"
                            title="Delete"
                            @click="store.control(item.hash, 'delete')"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
