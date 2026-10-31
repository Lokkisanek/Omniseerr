<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, LoaderCircle } from '@lucide/vue';

const props = defineProps({
    configs: { type: Object, required: true },
    setupComplete: { type: Boolean, default: false },
    servicesConfigured: { type: Boolean, default: false },
    hasAdminUser: { type: Boolean, default: false },
    needsAdmin: { type: Boolean, default: true },
});

const step = ref(props.needsAdmin && props.servicesConfigured ? 4 : 1);
const testing = ref(false);
const testMessage = ref('');
const testOk = ref(false);
const radarrMeta = ref({ profiles: [], folders: [] });
const sonarrMeta = ref({ profiles: [], folders: [], languageProfiles: [] });
const saving = ref(false);
const showLocalAdmin = ref(false);

const form = reactive({
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
    admin: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
});

const steps = computed(() => {
    const base = [
        { id: 1, title: 'Radarr', blurb: 'Movies & interactive search' },
        { id: 2, title: 'Sonarr', blurb: 'Optional TV library' },
        { id: 3, title: 'qBittorrent', blurb: 'Live transfer control' },
    ];
    if (props.needsAdmin || !props.hasAdminUser) {
        base.push({ id: 4, title: 'Admin', blurb: 'Plex or local owner' });
    }
    return base;
});

const maxStep = computed(() => (props.needsAdmin ? 4 : 3));

const canNext = computed(() => {
    if (step.value === 1) {
        return !!form.radarr.base_url && !!form.radarr.api_key
            && !!form.radarr.defaults.qualityProfileId
            && !!form.radarr.defaults.rootFolderPath;
    }
    if (step.value === 2) {
        if (!form.sonarr.enabled) return true;
        return !!form.sonarr.base_url && !!form.sonarr.api_key
            && !!form.sonarr.defaults.qualityProfileId
            && !!form.sonarr.defaults.rootFolderPath;
    }
    if (step.value === 3) {
        return !!form.qbittorrent.base_url && (
            !!form.qbittorrent.api_key
            || (!!form.qbittorrent.username && !!form.qbittorrent.password)
        );
    }
    if (!showLocalAdmin.value) return true;
    return !!form.admin.name
        && !!form.admin.email
        && !!form.admin.password
        && form.admin.password === form.admin.password_confirmation;
});

async function testCurrent() {
    if (step.value === 4) return;

    testing.value = true;
    testMessage.value = '';
    testOk.value = false;

    const payload = {
        1: { service: 'radarr', base_url: form.radarr.base_url, api_key: form.radarr.api_key },
        2: { service: 'sonarr', base_url: form.sonarr.base_url, api_key: form.sonarr.api_key },
        3: { service: 'qbittorrent', ...form.qbittorrent },
    }[step.value];

    try {
        const { data } = await axios.post('/setup/test', payload);
        testOk.value = true;
        testMessage.value = data.message || 'Connection OK';

        if (step.value === 1 && data.metadata) {
            radarrMeta.value = data.metadata;
            form.radarr.defaults.qualityProfileId ||= data.metadata.profiles?.[0]?.id ?? null;
            form.radarr.defaults.rootFolderPath ||= data.metadata.folders?.[0]?.path ?? null;
        }
        if (step.value === 2 && data.metadata) {
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
    if (step.value < maxStep.value) step.value += 1;
}

function back() {
    testMessage.value = '';
    if (step.value > 1) step.value -= 1;
}

function finish({ skipAdmin = false } = {}) {
    // Services already saved — just go to Plex login (don't re-post empty API keys).
    if (skipAdmin && props.servicesConfigured) {
        router.visit('/login');
        return;
    }

    saving.value = true;

    let payload;
    if (props.needsAdmin && props.servicesConfigured && !skipAdmin && showLocalAdmin.value) {
        payload = { admin: form.admin, admin_only: true };
    } else {
        payload = {
            radarr: form.radarr,
            sonarr: form.sonarr,
            qbittorrent: form.qbittorrent,
            skip_admin: skipAdmin || (props.needsAdmin && !showLocalAdmin.value),
        };
        if (props.needsAdmin && showLocalAdmin.value && !skipAdmin) {
            payload.admin = form.admin;
            payload.skip_admin = false;
        }
    }

    router.post('/setup', payload, {
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
            <p class="description">Connect your *arr stack. Catalog metadata uses a built-in TMDB key (same as Overseerr/Seerr).</p>
        </div>

        <div class="mb-6 grid gap-2" :class="needsAdmin ? 'grid-cols-4' : 'grid-cols-3'">
            <div
                v-for="s in steps"
                :key="s.id"
                class="rounded-md border px-2 py-3 sm:px-3"
                :class="step === s.id ? 'border-indigo-500 bg-indigo-600/20' : 'border-gray-700 bg-gray-800/60'"
            >
                <div class="text-[10px] font-bold tracking-wider text-gray-500 uppercase sm:text-xs">Step {{ s.id }}</div>
                <div class="text-xs font-medium text-white sm:text-sm">{{ s.title }}</div>
            </div>
        </div>

        <div class="panel p-6">
            <div v-if="step === 1" class="space-y-4">
                <h2 class="text-lg font-bold text-gray-100">Radarr</h2>
                <label>
                    <span>Base URL</span>
                    <input v-model="form.radarr.base_url" type="url" placeholder="http://radarr:7878" />
                </label>
                <label>
                    <span>API Key</span>
                    <input v-model="form.radarr.api_key" type="password" autocomplete="off" />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span>Default quality profile</span>
                        <select v-model="form.radarr.defaults.qualityProfileId">
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="p in radarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span>Default root folder</span>
                        <select v-model="form.radarr.defaults.rootFolderPath">
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="f in radarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div v-else-if="step === 2" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-100">Sonarr (optional)</h2>
                    <label class="checkbox-label mb-0 flex items-center gap-2 font-medium text-gray-300">
                        <input v-model="form.sonarr.enabled" type="checkbox" />
                        Enable
                    </label>
                </div>
                <template v-if="form.sonarr.enabled">
                    <label>
                        <span>Base URL</span>
                        <input v-model="form.sonarr.base_url" type="url" placeholder="http://sonarr:8989" />
                    </label>
                    <label>
                        <span>API Key</span>
                        <input v-model="form.sonarr.api_key" type="password" autocomplete="off" />
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label>
                            <span>Quality profile</span>
                            <select v-model="form.sonarr.defaults.qualityProfileId">
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="p in sonarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </label>
                        <label>
                            <span>Root folder</span>
                            <select v-model="form.sonarr.defaults.rootFolderPath">
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="f in sonarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                            </select>
                        </label>
                    </div>
                </template>
                <p v-else class="text-sm text-gray-400">Skip for now — movies-only mode works without Sonarr.</p>
            </div>

            <div v-else-if="step === 3" class="space-y-4">
                <h2 class="text-lg font-bold text-gray-100">qBittorrent</h2>
                <p class="text-sm text-gray-400">
                    Prefer API key (qBittorrent 5.2+: Web UI → Generate key).
                </p>
                <label>
                    <span>Base URL</span>
                    <input v-model="form.qbittorrent.base_url" type="url" placeholder="http://qbittorrent:8080" />
                </label>
                <label>
                    <span>API key <span class="label-tip">recommended</span></span>
                    <input v-model="form.qbittorrent.api_key" type="password" placeholder="qbt_..." autocomplete="off" />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span>Username <span class="label-tip">if no API key</span></span>
                        <input v-model="form.qbittorrent.username" type="text" autocomplete="username" />
                    </label>
                    <label>
                        <span>Password <span class="label-tip">if no API key</span></span>
                        <input v-model="form.qbittorrent.password" type="password" autocomplete="current-password" />
                    </label>
                </div>
            </div>

            <div v-else class="space-y-4">
                <h2 class="text-lg font-bold text-gray-100">Create admin</h2>
                <p class="text-sm text-gray-400">
                    Prefer Plex — first Plex sign-in becomes the admin (Overseerr-style). Local email is optional.
                </p>

                <button
                    type="button"
                    class="plex-button flex w-full items-center justify-center rounded-md px-4 py-2.5 text-sm font-medium text-white transition disabled:opacity-50"
                    :disabled="saving"
                    @click="finish({ skipAdmin: true })"
                >
                    <svg class="mr-2 h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M4 2h16v20H4z" opacity=".15" />
                        <path d="M8.5 3.5h3.2L15.5 12l-3.8 8.5H8.5L12.3 12 8.5 3.5z" />
                    </svg>
                    {{ saving ? 'Saving…' : 'Save & sign in with Plex' }}
                </button>

                <div class="relative py-1 text-center text-xs font-semibold tracking-wider text-gray-500 uppercase">
                    <span class="bg-gray-800 px-2">or</span>
                    <div class="absolute inset-x-0 top-1/2 -z-10 border-t border-gray-700" />
                </div>

                <button
                    type="button"
                    class="btn btn-default w-full"
                    @click="showLocalAdmin = !showLocalAdmin"
                >
                    {{ showLocalAdmin ? 'Hide local admin' : 'Create local email / password admin' }}
                </button>

                <template v-if="showLocalAdmin">
                    <label>
                        <span>Display name</span>
                        <input v-model="form.admin.name" type="text" autocomplete="name" />
                    </label>
                    <label>
                        <span>Email</span>
                        <input v-model="form.admin.email" type="email" autocomplete="email" />
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label>
                            <span>Password</span>
                            <input v-model="form.admin.password" type="password" autocomplete="new-password" />
                        </label>
                        <label>
                            <span>Confirm password</span>
                            <input v-model="form.admin.password_confirmation" type="password" autocomplete="new-password" />
                        </label>
                    </div>
                </template>

                <p v-if="servicesConfigured" class="text-sm text-gray-400">
                    Services already saved.
                    <Link href="/login" class="text-indigo-400 hover:text-indigo-300">Go to Plex login</Link>
                </p>
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
                        v-if="step < 4 && (step !== 2 || form.sonarr.enabled)"
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
                        v-if="step < maxStep"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!canNext"
                        @click="next"
                    >
                        Continue
                    </button>
                    <button
                        v-else-if="showLocalAdmin"
                        type="button"
                        class="btn btn-primary"
                        :disabled="!canNext || saving"
                        @click="finish({ skipAdmin: false })"
                    >
                        {{ saving ? 'Saving...' : 'Finish with local admin' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.plex-button {
    background-color: #cc7b19;
}
.plex-button:hover:not(:disabled) {
    background-color: #f19a30;
}
</style>
