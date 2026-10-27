<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { LoaderCircle, X } from '@lucide/vue';

const props = defineProps({
    media: { type: Object, required: true },
    profiles: { type: Array, default: () => [] },
    folders: { type: Array, default: () => [] },
    defaults: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'grabbed']);

const loading = ref(true);
const grabbingGuid = ref(null);
const radarrMovieId = ref(null);
const releases = ref([]);
const searchQuery = ref('');
const hideRejected = ref(false);
const resolutionFilter = ref('ALL');
const errorMsg = ref(null);
const qualityProfileId = ref(props.defaults.qualityProfileId ?? props.profiles[0]?.id ?? null);
const rootFolderPath = ref(props.defaults.rootFolderPath ?? props.folders[0]?.path ?? null);
const abortController = ref(null);
const closed = ref(false);

const fetchReleases = async () => {
    loading.value = true;
    errorMsg.value = null;
    abortController.value?.abort();
    abortController.value = new AbortController();

    try {
        const { data } = await axios.post(
            '/api/interactive-grab/prepare',
            {
                tmdb_id: props.media.tmdbId,
                media_type: 'movie',
                quality_profile_id: qualityProfileId.value,
                root_folder_path: rootFolderPath.value,
            },
            { signal: abortController.value.signal, timeout: 120000 },
        );

        if (closed.value) {
            if (data.radarrMovieId) {
                axios.delete(`/api/interactive-grab/cleanup/${data.radarrMovieId}`).catch(() => {});
            }
            return;
        }

        radarrMovieId.value = data.radarrMovieId;
        releases.value = data.releases;
    } catch (e) {
        if (axios.isCancel?.(e) || e.code === 'ERR_CANCELED') {
            return;
        }
        errorMsg.value = e.response?.data?.message || 'Failed to load releases from Radarr/Prowlarr.';
    } finally {
        loading.value = false;
    }
};

const filteredReleases = computed(() =>
    releases.value.filter((r) => {
        if (hideRejected.value && r.rejected) return false;
        if (resolutionFilter.value !== 'ALL' && String(r.resolution) !== resolutionFilter.value) return false;
        if (searchQuery.value && !r.title.toLowerCase().includes(searchQuery.value.toLowerCase())) return false;
        return true;
    }),
);

const grabRelease = async (release) => {
    grabbingGuid.value = release.guid;
    try {
        await axios.post('/api/interactive-grab/execute', {
            radarr_movie_id: radarrMovieId.value,
            guid: release.guid,
            indexer_id: release.indexerId,
        });
        radarrMovieId.value = null;
        emit('grabbed', release);
        emit('close');
    } catch (e) {
        alert('Failed to send torrent: ' + (e.response?.data?.message || e.message));
    } finally {
        grabbingGuid.value = null;
    }
};

const close = () => {
    closed.value = true;
    abortController.value?.abort();
    emit('close');
};

onBeforeUnmount(() => {
    closed.value = true;
    abortController.value?.abort();
    if (radarrMovieId.value) {
        axios.delete(`/api/interactive-grab/cleanup/${radarrMovieId.value}`).catch(() => {});
    }
});

onMounted(fetchReleases);
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/80 p-4 backdrop-blur-sm">
    <div class="relative flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-lg border border-gray-700 bg-gray-800 text-gray-100 shadow-2xl">
      <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-700 bg-gray-900/70 p-5">
        <div class="flex items-center gap-4">
          <img
            v-if="media.posterUrl"
            :src="media.posterUrl"
            class="h-[4.5rem] w-12 rounded-md object-cover shadow ring-1 ring-gray-700"
            alt=""
          />
          <div>
            <h2 class="text-xl font-bold text-white">
              {{ media.title }}
              <span class="font-medium text-gray-400">({{ media.year }})</span>
            </h2>
            <p class="text-xs text-gray-400">Interactive Prowlarr & Radarr release selector</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <select v-model="qualityProfileId" :disabled="loading || !!radarrMovieId">
            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <select v-model="rootFolderPath" :disabled="loading || !!radarrMovieId">
            <option v-for="f in folders" :key="f.path" :value="f.path">{{ f.path }}</option>
          </select>
          <input v-model="searchQuery" type="text" placeholder="Filter (CZ, Atmos, REMUX)..." />
          <select v-model="resolutionFilter">
            <option value="ALL">All resolutions</option>
            <option value="2160">4K</option>
            <option value="1080">1080p</option>
            <option value="720">720p</option>
          </select>
          <label class="mb-0 flex cursor-pointer items-center gap-2 text-xs font-medium text-gray-300 select-none">
            <input v-model="hideRejected" type="checkbox" />
            Hide rejected
          </label>
          <button type="button" class="btn btn-ghost btn-sm" @click="close">
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div v-if="loading" class="flex flex-1 flex-col items-center justify-center space-y-4 p-16">
        <LoaderCircle class="h-10 w-10 animate-spin text-indigo-400" />
        <p class="text-sm font-medium text-gray-300">
          Searching indexers via Prowlarr and scoring Custom Formats...
        </p>
      </div>

      <div v-else-if="errorMsg" class="flex flex-1 items-center justify-center p-12 text-sm text-red-400">
        {{ errorMsg }}
      </div>

      <div v-else class="flex-1 divide-y divide-gray-700/80 overflow-y-auto">
        <div
          v-for="rel in filteredReleases"
          :key="rel.guid"
          class="flex flex-col items-start justify-between gap-4 p-4 transition hover:bg-gray-700/40 md:flex-row md:items-center"
        >
          <div class="max-w-3xl space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-full border border-indigo-500 bg-indigo-600/80 px-2 py-0.5 text-xs font-semibold text-white">
                {{ rel.quality }}
              </span>
              <span class="rounded-full border border-gray-600 bg-gray-900 px-2 py-0.5 text-xs text-gray-300">{{ rel.indexer }}</span>
              <span
                v-if="rel.customFormatScore !== 0"
                class="rounded-full px-2 py-0.5 font-mono text-xs"
                :class="rel.customFormatScore > 0 ? 'border border-green-500 bg-green-500/20 text-green-300' : 'border border-red-500 bg-red-500/20 text-red-300'"
              >
                CF: {{ rel.customFormatScore > 0 ? '+' + rel.customFormatScore : rel.customFormatScore }}
              </span>
              <span
                v-for="lang in rel.languages"
                :key="lang"
                class="rounded-full border border-yellow-500/40 bg-yellow-500/20 px-2 py-0.5 text-xs text-yellow-200"
              >
                {{ lang }}
              </span>
            </div>
            <p class="break-all font-mono text-sm text-gray-100">{{ rel.title }}</p>
            <div v-if="rel.customFormats.length || rel.rejections.length" class="flex flex-wrap gap-1.5 pt-1">
              <span
                v-for="cf in rel.customFormats"
                :key="cf"
                class="rounded bg-gray-900 px-1.5 py-0.5 text-[11px] text-gray-300"
              >
                {{ cf }}
              </span>
              <span
                v-for="rej in rel.rejections"
                :key="rej"
                class="rounded border border-red-500/40 bg-red-900/60 px-1.5 py-0.5 text-[11px] text-red-300"
              >
                {{ rej }}
              </span>
            </div>
          </div>

          <div class="flex shrink-0 items-center gap-6 self-end md:self-center">
            <div class="text-right">
              <div class="text-sm font-semibold text-white">{{ rel.sizeFormatted }}</div>
              <div class="font-mono text-xs">
                <span class="text-green-400">{{ rel.seeders }} S</span>
                /
                <span class="text-yellow-400">{{ rel.leechers }} L</span>
              </div>
            </div>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="grabbingGuid === rel.guid"
              @click="grabRelease(rel)"
            >
              <span v-if="grabbingGuid === rel.guid">Sending...</span>
              <span v-else>Download</span>
            </button>
          </div>
        </div>

        <div v-if="filteredReleases.length === 0" class="p-12 text-center text-sm text-gray-400">
          No releases match the current filters.
        </div>
      </div>
    </div>
  </div>
</template>
