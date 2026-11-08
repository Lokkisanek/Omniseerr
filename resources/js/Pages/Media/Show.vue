<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock3,
    Download,
    ExternalLink,
    Play,
} from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import CastSlider from '../../Components/CastSlider.vue';
import InteractiveGrabModal from '../../Components/InteractiveGrabModal.vue';
import MediaSlider from '../../Components/MediaSlider.vue';

const props = defineProps({
    mediaType: { type: String, required: true },
    detail: { type: Object, required: true },
    recommendations: { type: Array, default: () => [] },
    similar: { type: Array, default: () => [] },
    radarr: { type: Object, default: () => ({ defaults: {}, profiles: [], folders: [] }) },
});

const page = usePage();
const selected = ref(null);

const canGrab = computed(() => {
    const can = page.props.auth?.user?.can || {};
    return props.mediaType === 'tv' ? !!can.grabTv : !!can.grabMovie;
});

const status = computed(() => props.detail.libraryStatus || 'UNREQUESTED');

const statusLabel = computed(() => {
    switch (status.value) {
        case 'AVAILABLE':
            return { text: 'Available', class: 'bg-green-600 text-white' };
        case 'DOWNLOADING':
        case 'PROCESSING':
            return { text: 'Downloading', class: 'bg-indigo-600 text-white' };
        case 'MONITORED':
        case 'PENDING':
            return { text: 'Requested', class: 'bg-blue-600 text-white' };
        default:
            return null;
    }
});

const metaLine = computed(() => {
    const parts = [];
    if (props.detail.runtime) parts.push(`${props.detail.runtime} minutes`);
    if (props.detail.genres?.length) parts.push(props.detail.genres.join(', '));
    if (props.detail.seasons) parts.push(`${props.detail.seasons} seasons`);
    return parts.join(' · ');
});

const money = (n) => {
    if (!n) return '—';
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n);
};

const grabItem = computed(() => ({
    tmdbId: props.detail.tmdbId,
    mediaType: props.detail.mediaType,
    title: props.detail.title,
    year: props.detail.year,
    overview: props.detail.overview,
    posterUrl: props.detail.posterUrl,
    libraryStatus: props.detail.libraryStatus,
}));

function openGrab() {
    if (canGrab.value && status.value !== 'AVAILABLE') {
        selected.value = grabItem.value;
    }
}
</script>

<template>
    <AppLayout :title="detail.title">
        <!-- Hero -->
        <div class="relative -mx-4 -mt-6 mb-8 overflow-hidden sm:-mx-6 lg:-mx-8">
            <div
                class="absolute inset-0 bg-cover bg-center"
                :style="detail.backdropUrl ? { backgroundImage: `url(${detail.backdropUrl})` } : {}"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/85 to-gray-900/40" />
            <div class="absolute inset-0 bg-gradient-to-r from-gray-900 via-gray-900/70 to-transparent" />

            <div class="relative z-10 flex flex-col gap-6 px-4 pt-10 pb-8 sm:px-6 sm:pt-14 lg:flex-row lg:px-8 lg:pb-12">
                <div class="mx-auto w-40 shrink-0 sm:mx-0 sm:w-48 lg:w-56">
                    <img
                        v-if="detail.posterUrl"
                        :src="detail.posterUrl"
                        :alt="detail.title"
                        class="w-full rounded-xl shadow-2xl ring-1 ring-white/10"
                    />
                    <div v-else class="aspect-[2/3] rounded-xl bg-gray-800" />
                </div>

                <div class="min-w-0 flex-1 self-end">
                    <div
                        v-if="statusLabel"
                        class="mb-3 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase"
                        :class="statusLabel.class"
                    >
                        {{ statusLabel.text }}
                    </div>

                    <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
                        {{ detail.title }}
                        <span v-if="detail.year" class="font-semibold text-gray-300">({{ detail.year }})</span>
                    </h1>

                    <p v-if="metaLine" class="mt-2 text-sm text-gray-300">{{ metaLine }}</p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button
                            v-if="canGrab && status !== 'AVAILABLE'"
                            type="button"
                            class="btn btn-primary"
                            @click="openGrab"
                        >
                            <Download class="mr-2 h-4 w-4" />
                            Request
                        </button>
                        <a
                            v-if="detail.trailerKey"
                            :href="`https://www.youtube.com/watch?v=${detail.trailerKey}`"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-default"
                        >
                            <Play class="mr-2 h-4 w-4" />
                            Watch Trailer
                        </a>
                        <a
                            v-if="detail.imdbId"
                            :href="`https://www.imdb.com/title/${detail.imdbId}`"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-ghost btn-sm"
                        >
                            <ExternalLink class="mr-1.5 h-4 w-4" />
                            IMDb
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="space-y-8">
                <div>
                    <p v-if="detail.tagline" class="mb-3 text-lg text-gray-300 italic">“{{ detail.tagline }}”</p>
                    <p class="max-w-3xl text-sm leading-6 text-gray-200 sm:text-base">{{ detail.overview }}</p>
                </div>

                <div v-if="detail.crew?.length">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-white">Crew</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                        <div v-for="(member, idx) in detail.crew" :key="`${member.id}-${member.job}-${idx}`">
                            <div class="text-xs tracking-wide text-gray-500 uppercase">{{ member.job }}</div>
                            <Link
                                :href="`/person/${member.id}`"
                                class="text-sm font-semibold text-white hover:text-indigo-300"
                            >
                                {{ member.name }}
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-if="detail.keywords?.length" class="flex flex-wrap gap-2">
                    <Link
                        v-for="keyword in detail.keywords"
                        :key="keyword.id"
                        :href="`/discover?type=${mediaType}&keyword=${keyword.id}&keyword_name=${encodeURIComponent(keyword.name)}`"
                        class="rounded-full bg-gray-800 px-3 py-1 text-xs font-medium text-gray-300 ring-1 ring-gray-700 transition hover:bg-gray-700 hover:text-white hover:ring-indigo-500"
                    >
                        {{ keyword.name }}
                    </Link>
                </div>

                <CastSlider :items="detail.cast || []" />

                <MediaSlider
                    title="Recommendations"
                    :items="recommendations"
                    @grab="selected = $event"
                />

                <MediaSlider
                    title="Similar Titles"
                    :items="similar"
                    @grab="selected = $event"
                />
            </div>

            <aside class="space-y-4">
                <div class="panel space-y-3 p-4 text-sm">
                    <div class="flex items-center gap-2">
                        <CheckCircle2 v-if="status === 'AVAILABLE'" class="h-4 w-4 text-green-400" />
                        <Clock3 v-else-if="status === 'MONITORED' || status === 'PENDING'" class="h-4 w-4 text-yellow-400" />
                        <div>
                            <div class="text-xs text-gray-500 uppercase">Library</div>
                            <div class="font-semibold text-white">{{ status }}</div>
                        </div>
                    </div>

                    <div v-if="detail.voteAverage">
                        <div class="text-xs text-gray-500 uppercase">Rating</div>
                        <div class="font-semibold text-white">{{ detail.voteAverage }} / 10</div>
                    </div>

                    <div v-if="detail.status">
                        <div class="text-xs text-gray-500 uppercase">Status</div>
                        <div class="font-semibold text-white">{{ detail.status }}</div>
                    </div>

                    <div v-if="detail.releaseDate">
                        <div class="text-xs text-gray-500 uppercase">Release Date</div>
                        <div class="font-semibold text-white">{{ detail.releaseDate }}</div>
                    </div>

                    <div v-if="mediaType === 'movie'">
                        <div class="text-xs text-gray-500 uppercase">Budget</div>
                        <div class="font-semibold text-white">{{ money(detail.budget) }}</div>
                    </div>

                    <div v-if="mediaType === 'movie'">
                        <div class="text-xs text-gray-500 uppercase">Revenue</div>
                        <div class="font-semibold text-white">{{ money(detail.revenue) }}</div>
                    </div>

                    <div v-if="detail.originalLanguage">
                        <div class="text-xs text-gray-500 uppercase">Original Language</div>
                        <div class="font-semibold text-white uppercase">{{ detail.originalLanguage }}</div>
                    </div>

                    <div v-if="detail.countries?.length">
                        <div class="text-xs text-gray-500 uppercase">Production</div>
                        <div class="font-semibold text-white">{{ detail.countries.join(', ') }}</div>
                    </div>

                    <div v-if="detail.studios?.length">
                        <div class="text-xs text-gray-500 uppercase">{{ mediaType === 'tv' ? 'Networks' : 'Studios' }}</div>
                        <div class="space-y-1">
                            <div v-for="studio in detail.studios" :key="studio.id" class="font-semibold text-white">
                                {{ studio.name }}
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
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
