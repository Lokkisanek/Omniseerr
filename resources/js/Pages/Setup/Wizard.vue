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
    return !!form.qbittorrent.base_url && !!form.qbittorrent.username && !!form.qbittorrent.password;
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
            <h1 class="text-3xl font-semibold tracking-tight text-white">Omniseerr</h1>
            <p class="mt-2 text-slate-400">Four-step setup. No .env editing required.</p>
        </div>

        <div class="mb-6 grid grid-cols-4 gap-2">
            <div
                v-for="s in steps"
                :key="s.id"
                class="rounded-xl border px-3 py-3"
                :class="step === s.id ? 'border-blue-400/40 bg-blue-500/10' : 'border-white/10 bg-white/5'"
            >
                <div class="text-xs uppercase tracking-wider text-slate-500">Step {{ s.id }}</div>
                <div class="text-sm font-medium text-white">{{ s.title }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-slate-900/70 p-6 backdrop-blur-xl">
            <div v-if="step === 1" class="space-y-4">
                <h2 class="text-lg font-medium text-white">TMDB API</h2>
                <label class="block text-sm text-slate-400">
                    API Key
                    <input v-model="form.tmdb.api_key" type="password" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                </label>
                <label class="block text-sm text-slate-400">
                    Base URL
                    <input v-model="form.tmdb.base_url" type="text" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                </label>
            </div>

            <div v-else-if="step === 2" class="space-y-4">
                <h2 class="text-lg font-medium text-white">Radarr</h2>
                <label class="block text-sm text-slate-400">
                    Base URL
                    <input v-model="form.radarr.base_url" placeholder="http://radarr:7878" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                </label>
                <label class="block text-sm text-slate-400">
                    API Key
                    <input v-model="form.radarr.api_key" type="password" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm text-slate-400">
                        Default quality profile
                        <select v-model="form.radarr.defaults.qualityProfileId" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white">
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="p in radarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-400">
                        Default root folder
                        <select v-model="form.radarr.defaults.rootFolderPath" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white">
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="f in radarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div v-else-if="step === 3" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-white">Sonarr (optional)</h2>
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input v-model="form.sonarr.enabled" type="checkbox" class="rounded border-white/20 bg-slate-800 text-blue-500" />
                        Enable
                    </label>
                </div>
                <template v-if="form.sonarr.enabled">
                    <label class="block text-sm text-slate-400">
                        Base URL
                        <input v-model="form.sonarr.base_url" placeholder="http://sonarr:8989" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                    </label>
                    <label class="block text-sm text-slate-400">
                        API Key
                        <input v-model="form.sonarr.api_key" type="password" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm text-slate-400">
                            Quality profile
                            <select v-model="form.sonarr.defaults.qualityProfileId" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white">
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="p in sonarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </label>
                        <label class="block text-sm text-slate-400">
                            Root folder
                            <select v-model="form.sonarr.defaults.rootFolderPath" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white">
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="f in sonarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                            </select>
                        </label>
                    </div>
                </template>
                <p v-else class="text-sm text-slate-400">Skip for now — movies-only mode works without Sonarr.</p>
            </div>

            <div v-else class="space-y-4">
                <h2 class="text-lg font-medium text-white">qBittorrent</h2>
                <label class="block text-sm text-slate-400">
                    Base URL
                    <input v-model="form.qbittorrent.base_url" placeholder="http://qbittorrent:8080" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm text-slate-400">
                        Username
                        <input v-model="form.qbittorrent.username" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                    </label>
                    <label class="block text-sm text-slate-400">
                        Password
                        <input v-model="form.qbittorrent.password" type="password" class="mt-1 w-full rounded-lg border border-white/10 bg-slate-950 px-3 py-2 text-white" />
                    </label>
                </div>
            </div>

            <div v-if="testMessage" class="mt-4 rounded-lg border px-3 py-2 text-sm" :class="testOk ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-rose-500/30 bg-rose-500/10 text-rose-300'">
                {{ testMessage }}
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <button
                    class="rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-300 hover:bg-white/5 disabled:opacity-40"
                    :disabled="step === 1"
                    @click="back"
                >
                    Back
                </button>
                <div class="flex gap-2">
                    <button
                        v-if="step !== 3 || form.sonarr.enabled"
                        class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-4 py-2 text-sm text-slate-200 hover:bg-white/5"
                        :disabled="testing"
                        @click="testCurrent"
                    >
                        <LoaderCircle v-if="testing" class="h-4 w-4 animate-spin" />
                        <Check v-else-if="testOk" class="h-4 w-4 text-emerald-400" />
                        Test Connection
                    </button>
                    <button
                        v-if="step < 4"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-40"
                        :disabled="!canNext"
                        @click="next"
                    >
                        Continue
                    </button>
                    <button
                        v-else
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-40"
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
