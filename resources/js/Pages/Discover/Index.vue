<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import InteractiveGrabModal from '../../Components/InteractiveGrabModal.vue';
import TitleCard from '../../Components/TitleCard.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '', type: 'movie' }) },
    radarr: { type: Object, default: () => ({ defaults: {}, profiles: [], folders: [] }) },
});

const type = ref(props.filters.type || 'movie');
const selected = ref(null);

watch(
    () => props.filters.type,
    (value) => {
        type.value = value || 'movie';
    },
);

function setType(next) {
    type.value = next;
    router.get(
        '/discover',
        { q: props.filters.q || '', type: next },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <AppLayout title="Discover">
        <div class="section">
            <div class="slider-header items-end justify-between gap-4">
                <div>
                    <h1 class="heading">
                        {{ filters.q ? `Results for “${filters.q}”` : 'Discover' }}
                    </h1>
                    <p class="description">
                        Browse TMDB and grab releases with Radarr Custom Format scoring.
                    </p>
                </div>

                <div class="flex rounded-md border border-gray-600 bg-gray-800 p-0.5">
                    <button
                        type="button"
                        class="rounded px-3 py-1.5 text-sm font-medium transition"
                        :class="type === 'movie' ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:text-white'"
                        @click="setType('movie')"
                    >
                        Movies
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-1.5 text-sm font-medium transition"
                        :class="type === 'tv' ? 'bg-indigo-600 text-white' : 'text-gray-300 hover:text-white'"
                        @click="setType('tv')"
                    >
                        Series
                    </button>
                </div>
            </div>

            <ul class="cards-vertical">
                <li v-for="item in items" :key="`${item.mediaType}-${item.tmdbId}`">
                    <TitleCard :item="item" @grab="selected = $event" />
                </li>
            </ul>

            <div
                v-if="items.length === 0"
                class="panel mt-6 border-dashed px-6 py-16 text-center text-sm text-gray-400"
            >
                No results. Try another search.
            </div>
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
