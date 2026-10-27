<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, LoaderCircle } from '@lucide/vue';

const props = defineProps({
    configs: { type: Object, required: true },
    setupComplete: { type: Boolean, default: false },
});

const step = ref(1);
const testing = ref(false);
const testMessage = ref('');
const testOk = ref(false);
const radarrMeta = ref({ profiles: [], folders: [] });
const sonarrMeta = ref({ profiles: [], folders: [], languageProfiles: [] });
const saving = ref(false);

const form = reactive({
    tmdb: {
        api_key: '',
        base_url: props.configs.tmdb?.base_url || 'https://api.themoviedb.org/3',
    },
    radarr: {
        base_url: props.configs.radarr?.base_url || '',
        api_key: '',
        defaults: {
            qualityProfileId: props.configs.radarr?.defaults?.qualityProfileId || null,
            rootFolderPath: props.configs.radarr?.defaults?.rootFolderPath || null,
        },
    },
    sonarr: {
        enabled: !!props.configs.sonarr?.is_enabled,
        base_url: props.configs.sonarr?.base_url || '',
        api_key: '',
        defaults: {
            qualityProfileId: props.configs.sonarr?.defaults?.qualityProfileId || null,
            rootFolderPath: props.configs.sonarr?.defaults?.rootFolderPath || null,
            languageProfileId: props.configs.sonarr?.defaults?.languageProfileId || null,
        },
    },
    qbittorrent: {
        base_url: props.configs.qbittorrent?.base_url || '',
        api_key: '',
        username: props.configs.qbittorrent?.username || 'admin',
        password: '',
    },
});

const steps = [
    { id: 1, title: 'TMDB', blurb: 'Catalog metadata' },
    { id: 2, title: 'Radarr', blurb: 'Movies & interactive search' },
    { id: 3, title: 'Sonarr', blurb: 'Optional TV library' },
    { id: 4, title: 'qBittorrent', blurb: 'Live transfer control' },
];

const canNext = computed(() => {
    if (step.value === 1) return !!form.tmdb.api_key;
    if (step.value === 2) {
        return !!form.radarr.base_url && !!form.radarr.api_key
            && !!form.radarr.defaults.qualityProfileId
            && !!form.radarr.defaults.rootFolderPath;
    }
    if (step.value === 3) {
        if (!form.sonarr.enabled) return true;
        return !!form.sonarr.base_url && !!form.sonarr.api_key
            && !!form.sonarr.defaults.qualityProfileId
            && !!form.sonarr.defaults.rootFolderPath;
    }
    return !!form.qbittorrent.base_url && (
        !!form.qbittorrent.api_key
        || (!!form.qbittorrent.username && !!form.qbittorrent.password)
    );
});

async function testCurrent() {
    testing.value = true;
    testMessage.value = '';
    testOk.value = false;

    const payload = {
        1: { service: 'tmdb', ...form.tmdb },
        2: { service: 'radarr', base_url: form.radarr.base_url, api_key: form.radarr.api_key },
        3: { service: 'sonarr', base_url: form.sonarr.base_url, api_key: form.sonarr.api_key },
        4: { service: 'qbittorrent', ...form.qbittorrent },
    }[step.value];

    try {
        const { data } = await axios.post('/setup/test', payload);
        testOk.value = true;
        testMessage.value = data.message || 'Connection OK';

        if (step.value === 2 && data.metadata) {
            radarrMeta.value = data.metadata;
            form.radarr.defaults.qualityProfileId ||= data.metadata.profiles?.[0]?.id ?? null;
            form.radarr.defaults.rootFolderPath ||= data.metadata.folders?.[0]?.path ?? null;
        }
        if (step.value === 3 && data.metadata) {
            sonarrMeta.value = data.metadata;
            form.sonarr.defaults.qualityProfileId ||= data.metadata.profiles?.[0]?.id ?? null;
            form.sonarr.defaults.rootFolderPath ||= data.metadata.folders?.[0]?.path ?? null;
            form.sonarr.defaults.languageProfileId ||= data.metadata.languageProfiles?.[0]?.id ?? null;
        }
    } catch (e) {
        testOk.value = false;
        testMessage.value = e.response?.data?.message || e.message;
    } finally {
        testing.value = false;
    }
}

function next() {
    testMessage.value = '';
    if (step.value < 4) step.value += 1;
}

function back() {
    testMessage.value = '';
    if (step.value > 1) step.value -= 1;
}

function finish() {
    saving.value = true;
    router.post('/setup', form, {
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <div class="mx-auto flex min-h-screen max-w-3xl flex-col justify-center px-4 py-10">
        <div class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight">
                <span class="text-brand">Omni</span><span class="text-white">seerr</span>
            </h1>
            <p class="description">Four-step setup. No .env editing required.</p>
        </div>

        <div class="mb-6 grid grid-cols-4 gap-2">
            <div
                v-for="s in steps"
                :key="s.id"
                class="rounded-md border px-3 py-3"
                :class="step === s.id ? 'border-indigo-500 bg-indigo-600/20' : 'border-gray-700 bg-gray-800/60'"
            >
                <div class="text-xs font-bold tracking-wider text-gray-500 uppercase">Step {{ s.id }}</div>
                <div class="text-sm font-medium text-white">{{ s.title }}</div>
            </div>
        </div>

        <div class="panel p-6">
            <div v-if="step === 1" class="space-y-4">
                <h2 class="text-lg font-bold text-gray-100">TMDB API</h2>
                <label>
                    API Key
                    <input v-model="form.tmdb.api_key" type="password" class="mt-1" />
                </label>
                <label>
                    Base URL
                    <input v-model="form.tmdb.base_url" type="text" class="mt-1" />
                </label>
            </div>

            <div v-else-if="step === 2" class="space-y-4">
                <h2 class="text-lg font-bold text-gray-100">Radarr</h2>
                <label>
                    Base URL
                    <input v-model="form.radarr.base_url" placeholder="http://radarr:7878" class="mt-1" />
                </label>
                <label>
                    API Key
                    <input v-model="form.radarr.api_key" type="password" class="mt-1" />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        Default quality profile
                        <select v-model="form.radarr.defaults.qualityProfileId" class="mt-1">
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="p in radarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label>
                        Default root folder
                        <select v-model="form.radarr.defaults.rootFolderPath" class="mt-1">
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="f in radarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div v-else-if="step === 3" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-100">Sonarr (optional)</h2>
                    <label class="checkbox-label mb-0 flex items-center gap-2 font-medium text-gray-300">
                        <input v-model="form.sonarr.enabled" type="checkbox" />
                        Enable
                    </label>
                </div>
                <template v-if="form.sonarr.enabled">
                    <label>
                        Base URL
                        <input v-model="form.sonarr.base_url" placeholder="http://sonarr:8989" class="mt-1" />
                    </label>
                    <label>
                        API Key
                        <input v-model="form.sonarr.api_key" type="password" class="mt-1" />
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label>
                            Quality profile
                            <select v-model="form.sonarr.defaults.qualityProfileId" class="mt-1">
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="p in sonarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </label>
                        <label>
                            Root folder
                            <select v-model="form.sonarr.defaults.rootFolderPath" class="mt-1">
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="f in sonarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                            </select>
                        </label>
                    </div>
                </template>
                <p v-else class="text-sm text-gray-400">Skip for now — movies-only mode works without Sonarr.</p>
            </div>

            <div v-else class="space-y-4">
                <h2 class="text-lg font-bold text-gray-100">qBittorrent</h2>
                <p class="text-sm text-gray-400">
                    Prefer API key (qBittorrent 5.2+: Web UI → Generate key). Username/password still works as fallback.
                </p>
                <label>
                    Base URL
                    <input v-model="form.qbittorrent.base_url" placeholder="http://qbittorrent:8080" class="mt-1" />
                </label>
                <label>
                    API key <span class="label-tip">recommended</span>
                    <input v-model="form.qbittorrent.api_key" type="password" placeholder="qbt_..." class="mt-1" />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        Username <span class="label-tip">if no API key</span>
                        <input v-model="form.qbittorrent.username" class="mt-1" />
                    </label>
                    <label>
                        Password <span class="label-tip">if no API key</span>
                        <input v-model="form.qbittorrent.password" type="password" class="mt-1" />
                    </label>
                </div>
            </div>

            <div v-if="testMessage" :class="testOk ? 'form-success' : 'form-error'">
                {{ testMessage }}
            </div>

            <div class="actions flex flex-wrap items-center justify-between gap-3">
                <button type="button" class="btn btn-default" :disabled="step === 1" @click="back">
                    Back
                </button>
                <div class="flex gap-2">
                    <button
                        v-if="step !== 3 || form.sonarr.enabled"
                        type="button"
                        class="btn btn-default"
                        :disabled="testing"
                        @click="testCurrent"
                    >
                        <LoaderCircle v-if="testing" class="mr-2 h-4 w-4 animate-spin" />
                        <Check v-else-if="testOk" class="mr-2 h-4 w-4 text-green-400" />
                        Test Connection
                    </button>
                    <button
                        v-if="step < 4"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!canNext"
                        @click="next"
                    >
                        Continue
                    </button>
                    <button
                        v-else
                        type="button"
                        class="btn btn-primary"
                        :disabled="!canNext || saving"
                        @click="finish"
                    >
                        {{ saving ? 'Saving...' : 'Finish setup' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
