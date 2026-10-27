<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Download, Search } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import InteractiveGrabModal from '../../Components/InteractiveGrabModal.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', type: 'movie' }) },
    radarr: { type: Object, default: () => ({ defaults: {}, profiles: [], folders: [] }) },
});

const q = ref(props.filters.q || '');
const type = ref(props.filters.type || 'movie');
const selected = ref(null);

function search() {
    router.get('/discover', { q: q.value, type: type.value }, { preserveState: true, replace: true });
}

function statusClass(status) {
    return {
        AVAILABLE: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
        DOWNLOADING: 'bg-blue-500/20 text-blue-300 border-blue-500/30 animate-pulse',
        MONITORED: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        UNREQUESTED: 'hidden',
    }[status] || 'hidden';
}
</script>

<template>
    <AppLayout title="Discover">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Discover</h1>
                <p class="mt-1 text-sm text-slate-400">Browse TMDB and grab releases with Custom Format scoring.</p>
            </div>
            <form class="flex flex-wrap gap-2" @submit.prevent="search">
                <select
                    v-model="type"
                    class="rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-sm text-white"
                    @change="search"
                >
                    <option value="movie">Movies</option>
                    <option value="tv">TV</option>
                </select>
                <div class="relative">
                    <Search class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-500" />
                    <input
                        v-model="q"
                        type="search"
                        placeholder="Search titles..."
                        class="rounded-lg border border-white/10 bg-slate-950 py-2 pr-3 pl-9 text-sm text-white"
                    />
                </div>
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                    Search
                </button>
            </form>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            <article
                v-for="item in items"
                :key="`${item.mediaType}-${item.tmdbId}`"
                class="group overflow-hidden rounded-2xl border border-white/10 bg-white/5"
            >
                <div class="relative aspect-[2/3] bg-slate-900">
                    <img
                        v-if="item.posterUrl"
                        :src="item.posterUrl"
                        :alt="item.title"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                    />
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-3">
                        <div class="line-clamp-2 text-sm font-medium text-white">{{ item.title }}</div>
                        <div class="text-xs text-slate-400">{{ item.year || '—' }}</div>
                    </div>
                    <span
                        v-if="item.libraryStatus !== 'UNREQUESTED'"
                        class="absolute top-2 right-2 rounded-md border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                        :class="statusClass(item.libraryStatus)"
                    >
                        {{ item.libraryStatus }}
                    </span>
                </div>
                <div class="p-3">
                    <button
                        v-if="item.mediaType === 'movie'"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600/90 px-3 py-2 text-sm font-medium text-white hover:bg-blue-500"
                        @click="selected = item"
                    >
                        <Download class="h-4 w-4" />
                        Interactive Grab
                    </button>
                    <div v-else class="rounded-xl border border-white/10 px-3 py-2 text-center text-xs text-slate-400">
                        TV interactive grab coming next
                    </div>
                </div>
            </article>
        </div>

        <div v-if="items.length === 0" class="rounded-2xl border border-dashed border-white/10 p-12 text-center text-slate-400">
            No results. Try another search.
        </div>

        <InteractiveGrabModal
            v-if="selected"
            :media="selected"
            :profiles="radarr.profiles"
            :folders="radarr.folders"
            :defaults="radarr.defaults"
            @close="selected = null"
            @grabbed="selected = null"
        />
    </AppLayout>
</template>
