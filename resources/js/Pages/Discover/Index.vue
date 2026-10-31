<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Filter } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import GenreSlider from '../../Components/GenreSlider.vue';
import InteractiveGrabModal from '../../Components/InteractiveGrabModal.vue';
import LogoSlider from '../../Components/LogoSlider.vue';
import MediaSlider from '../../Components/MediaSlider.vue';
import TitleCard from '../../Components/TitleCard.vue';

const props = defineProps({
    mode: { type: String, default: 'home' },
    pageTitle: { type: String, default: 'Discover' },
    sections: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    providers: { type: Array, default: () => [] },
    movieGenres: { type: Array, default: () => [] },
    tvGenres: { type: Array, default: () => [] },
    studios: { type: Array, default: () => [] },
    sortOptions: { type: Object, default: () => ({}) },
    activeFilters: { type: Number, default: 0 },
    filters: {
        type: Object,
        default: () => ({
            q: '',
            type: null,
            provider: null,
            genre: null,
            studio: null,
            sort: 'popularity.desc',
            region: 'CZ',
        }),
    },
    radarr: { type: Object, default: () => ({ defaults: {}, profiles: [], folders: [] }) },
});

const selected = ref(null);
const sort = ref(props.filters.sort || 'popularity.desc');

watch(
    () => props.filters.sort,
    (value) => {
        sort.value = value || 'popularity.desc';
    },
);

const isList = computed(() => props.mode === 'list' || props.mode === 'search');

const clearHref = computed(() => {
    if (props.filters.type) return `/discover?type=${props.filters.type}`;
    return '/discover';
});

const showBack = computed(() =>
    !!(props.filters.q || props.filters.provider || props.filters.genre || props.filters.studio),
);

function applySort() {
    const params = {
        type: props.filters.type || undefined,
        q: props.filters.q || undefined,
        provider: props.filters.provider || undefined,
        genre: props.filters.genre || undefined,
        studio: props.filters.studio || undefined,
        sort: sort.value,
    };
    Object.keys(params).forEach((key) => {
        if (params[key] === undefined || params[key] === null || params[key] === '') {
            delete params[key];
        }
    });
    router.get('/discover', params, { preserveState: true, replace: true });
}
</script>

<template>
    <AppLayout :title="pageTitle">
        <div class="section !mt-1">
            <!-- LIST: Movies / Series / categories (Seerr grid) -->
            <template v-if="isList">
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p v-if="showBack" class="mb-1">
                            <Link :href="clearHref" class="text-sm text-indigo-400 hover:text-indigo-300">
                                ← Back
                            </Link>
                        </p>
                        <h1 class="text-3xl font-extrabold tracking-tight text-indigo-400 sm:text-4xl">
                            {{ pageTitle }}
                        </h1>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="relative mb-0">
                            <span class="sr-only">Sort</span>
                            <select
                                v-model="sort"
                                class="!mt-0 min-w-[12rem] !py-2 pr-8 text-sm"
                                @change="applySort"
                            >
                                <option v-for="(label, value) in sortOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="button"
                            class="btn btn-default btn-sm pointer-events-none"
                            :class="activeFilters > 0 ? '!border-indigo-500 !text-indigo-300' : ''"
                        >
                            <Filter class="mr-1.5 h-3.5 w-3.5" />
                            {{ activeFilters }} Active Filter{{ activeFilters === 1 ? '' : 's' }}
                        </button>
                    </div>
                </div>

                <ul class="cards-vertical cards-dense">
                    <li v-for="item in items" :key="`${item.mediaType}-${item.tmdbId}`">
                        <TitleCard :item="item" @grab="selected = $event" />
                    </li>
                </ul>

                <div
                    v-if="items.length === 0"
                    class="panel mt-6 border-dashed px-6 py-16 text-center text-sm text-gray-400"
                >
                    No titles found.
                </div>
            </template>

            <!-- HOME: Discover carousels -->
            <template v-else>
                <div class="space-y-8">
                    <template v-for="section in sections" :key="section.key">
                        <MediaSlider
                            v-if="section.kind === 'media'"
                            :title="section.title"
                            :items="section.items"
                            :href="section.href || null"
                            @grab="selected = $event"
                        />
                        <GenreSlider
                            v-else-if="section.kind === 'genres'"
                            :title="section.title"
                            :items="section.items"
                            :media-type="section.mediaType || 'movie'"
                        />
                        <LogoSlider
                            v-else-if="section.kind === 'studios'"
                            :title="section.title"
                            :items="section.items"
                            kind="studios"
                        />
                        <LogoSlider
                            v-else-if="section.kind === 'networks'"
                            :title="section.title"
                            :items="section.items"
                            kind="networks"
                        />
                    </template>
                </div>

                <div
                    v-if="sections.length === 0"
                    class="panel mt-6 border-dashed px-6 py-16 text-center text-sm text-gray-400"
                >
                    Nothing to show right now.
                </div>
            </template>
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
