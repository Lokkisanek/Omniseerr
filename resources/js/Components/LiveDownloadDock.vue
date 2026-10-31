<script setup>
import { ChevronDown, ChevronUp, Pause, Play, Trash2 } from '@lucide/vue';
import { storeToRefs } from 'pinia';
import { computed, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useDownloadsStore } from '../Stores/downloads';

const STORAGE_KEY = 'omniseerr.downloadDockCollapsed';

const store = useDownloadsStore();
const { queue, activeCount } = storeToRefs(store);

const collapsed = ref(true);

const visible = computed(() =>
    queue.value.filter((item) => (item.progress ?? 100) < 100).slice(0, 4),
);

const primary = computed(() => visible.value[0] ?? null);

const averageProgress = computed(() => {
    if (!visible.value.length) return 0;
    const sum = visible.value.reduce((acc, item) => acc + (item.progress ?? 0), 0);
    return Math.round(sum / visible.value.length);
});

onMounted(() => {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved !== null) collapsed.value = saved === '1';
    } catch {
        // ignore
    }
});

watch(collapsed, (value) => {
    try {
        localStorage.setItem(STORAGE_KEY, value ? '1' : '0');
    } catch {
        // ignore
    }
});

function toggle() {
    collapsed.value = !collapsed.value;
}
</script>

<template>
    <div
        v-if="activeCount > 0"
        class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-700 bg-gray-900/95 backdrop-blur lg:pl-64"
    >
        <!-- Compact bar (default) -->
        <div class="flex items-center gap-3 px-4 py-2 sm:px-6 lg:px-8">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                @click="toggle"
            >
                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-indigo-600 px-1.5 text-xs font-semibold text-white">
                    {{ activeCount }}
                </span>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-white">
                        {{ primary?.cleanTitle || 'Active downloads' }}
                        <span v-if="activeCount > 1" class="text-gray-400"> +{{ activeCount - 1 }}</span>
                    </div>
                    <div class="mt-1 h-1 overflow-hidden rounded-full bg-gray-700">
                        <div
                            class="h-full rounded-full bg-indigo-500 transition-all"
                            :style="{ width: `${averageProgress}%` }"
                        />
                    </div>
                </div>
                <span class="hidden text-xs text-gray-400 sm:inline">{{ averageProgress }}%</span>
                <component :is="collapsed ? ChevronUp : ChevronDown" class="h-4 w-4 shrink-0 text-gray-400" />
            </button>

            <div v-if="primary" class="flex shrink-0 items-center gap-1">
                <button type="button" class="btn btn-default btn-sm !px-2" title="Pause" @click="store.control(primary.hash, 'pause')">
                    <Pause class="h-3.5 w-3.5" />
                </button>
                <button type="button" class="btn btn-default btn-sm !px-2" title="Resume" @click="store.control(primary.hash, 'resume')">
                    <Play class="h-3.5 w-3.5" />
                </button>
                <button type="button" class="btn btn-danger btn-sm !px-2" title="Delete" @click="store.control(primary.hash, 'delete')">
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>

            <Link href="/activity" class="btn btn-ghost btn-sm hidden sm:inline-flex">Activity</Link>
        </div>

        <!-- Expanded details -->
        <div v-if="!collapsed" class="space-y-2 border-t border-gray-800 px-4 py-3 sm:px-6 lg:px-8">
            <div
                v-for="item in visible"
                :key="item.hash"
                class="flex items-center gap-3"
            >
                <img
                    v-if="item.posterUrl"
                    :src="item.posterUrl"
                    class="h-10 w-7 rounded object-cover ring-1 ring-gray-700"
                    alt=""
                />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm text-white">{{ item.cleanTitle }}</div>
                    <div class="mt-0.5 flex items-center justify-between text-[11px] text-gray-400">
                        <span>{{ item.progress }}% · {{ item.dlSpeedHuman }}</span>
                        <span class="uppercase">{{ item.state }}</span>
                    </div>
                    <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-gray-700">
                        <div
                            class="h-full rounded-full bg-indigo-500 transition-all"
                            :style="{ width: `${item.progress}%` }"
                        />
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
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
</template>
