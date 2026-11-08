<script setup>
import { reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, LoaderCircle, Save } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    configs: { type: Object, required: true },
});

const page = usePage();
const saving = ref(false);
const testing = ref(null);
const testMessage = ref('');
const testOk = ref(false);
const radarrMeta = ref({ profiles: [], folders: [] });
const sonarrMeta = ref({ profiles: [], folders: [], languageProfiles: [] });

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
});

async function testService(service) {
    testing.value = service;
    testMessage.value = '';
    testOk.value = false;

    const payload = {
        radarr: {
            service: 'radarr',
            base_url: form.radarr.base_url,
            api_key: form.radarr.api_key,
        },
        sonarr: {
            service: 'sonarr',
            base_url: form.sonarr.base_url,
            api_key: form.sonarr.api_key,
        },
        qbittorrent: {
            service: 'qbittorrent',
            ...form.qbittorrent,
        },
    }[service];

    try {
        const { data } = await axios.post('/settings/test', payload);
        testOk.value = true;
        testMessage.value = data.message || `${service} connection OK`;

        if (service === 'radarr' && data.metadata) {
            radarrMeta.value = data.metadata;
            form.radarr.defaults.qualityProfileId ||= data.metadata.profiles?.[0]?.id ?? null;
            form.radarr.defaults.rootFolderPath ||= data.metadata.folders?.[0]?.path ?? null;
        }
        if (service === 'sonarr' && data.metadata) {
            sonarrMeta.value = data.metadata;
            form.sonarr.defaults.qualityProfileId ||= data.metadata.profiles?.[0]?.id ?? null;
            form.sonarr.defaults.rootFolderPath ||= data.metadata.folders?.[0]?.path ?? null;
            form.sonarr.defaults.languageProfileId ||= data.metadata.languageProfiles?.[0]?.id ?? null;
        }
    } catch (e) {
        testOk.value = false;
        testMessage.value = e.response?.data?.message || e.message;
    } finally {
        testing.value = null;
    }
}

function save() {
    saving.value = true;
    router.put('/settings', {
        radarr: form.radarr,
        sonarr: form.sonarr,
        qbittorrent: form.qbittorrent,
    }, {
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <AppLayout title="Settings">
        <div class="mx-auto max-w-3xl space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Settings</h1>
                <p class="mt-1 text-sm text-gray-400">
                    Connect Radarr, Sonarr, and qBittorrent. Leave API keys blank to keep the current value.
                </p>
            </div>

            <div
                v-if="page.props.flash?.success"
                class="form-success"
            >
                {{ page.props.flash.success }}
            </div>
            <div
                v-if="testMessage"
                :class="testOk ? 'form-success' : 'form-error'"
            >
                {{ testMessage }}
            </div>

            <section class="panel space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-100">Radarr</h2>
                    <button
                        type="button"
                        class="btn btn-default btn-sm"
                        :disabled="testing === 'radarr'"
                        @click="testService('radarr')"
                    >
                        <LoaderCircle v-if="testing === 'radarr'" class="mr-2 h-4 w-4 animate-spin" />
                        <Check v-else-if="testOk && testing === null" class="mr-2 h-4 w-4 text-green-400" />
                        Test Connection
                    </button>
                </div>
                <label>
                    <span>Base URL</span>
                    <input v-model="form.radarr.base_url" type="url" placeholder="http://radarr:7878" />
                </label>
                <label>
                    <span>API Key <span class="label-tip">leave blank to keep</span></span>
                    <input
                        v-model="form.radarr.api_key"
                        type="password"
                        :placeholder="configs.radarr?.has_api_key ? '••••••••' : 'Required'"
                        autocomplete="off"
                    />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span>Quality profile</span>
                        <select v-model="form.radarr.defaults.qualityProfileId">
                            <option
                                v-if="form.radarr.defaults.qualityProfileId"
                                :value="form.radarr.defaults.qualityProfileId"
                            >
                                Current (#{{ form.radarr.defaults.qualityProfileId }})
                            </option>
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="p in radarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label>
                        <span>Root folder</span>
                        <select v-model="form.radarr.defaults.rootFolderPath">
                            <option
                                v-if="form.radarr.defaults.rootFolderPath"
                                :value="form.radarr.defaults.rootFolderPath"
                            >
                                {{ form.radarr.defaults.rootFolderPath }}
                            </option>
                            <option :value="null" disabled>Select after Test Connection</option>
                            <option v-for="f in radarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                        </select>
                    </label>
                </div>
            </section>

            <section class="panel space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-100">Sonarr</h2>
                    <div class="flex items-center gap-3">
                        <label class="checkbox-label mb-0 flex items-center gap-2 font-medium text-gray-300">
                            <input v-model="form.sonarr.enabled" type="checkbox" />
                            Enable
                        </label>
                        <button
                            v-if="form.sonarr.enabled"
                            type="button"
                            class="btn btn-default btn-sm"
                            :disabled="testing === 'sonarr'"
                            @click="testService('sonarr')"
                        >
                            <LoaderCircle v-if="testing === 'sonarr'" class="mr-2 h-4 w-4 animate-spin" />
                            Test Connection
                        </button>
                    </div>
                </div>
                <template v-if="form.sonarr.enabled">
                    <label>
                        <span>Base URL</span>
                        <input v-model="form.sonarr.base_url" type="url" placeholder="http://sonarr:8989" />
                    </label>
                    <label>
                        <span>API Key <span class="label-tip">leave blank to keep</span></span>
                        <input
                            v-model="form.sonarr.api_key"
                            type="password"
                            :placeholder="configs.sonarr?.has_api_key ? '••••••••' : 'Required'"
                            autocomplete="off"
                        />
                    </label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label>
                            <span>Quality profile</span>
                            <select v-model="form.sonarr.defaults.qualityProfileId">
                                <option
                                    v-if="form.sonarr.defaults.qualityProfileId"
                                    :value="form.sonarr.defaults.qualityProfileId"
                                >
                                    Current (#{{ form.sonarr.defaults.qualityProfileId }})
                                </option>
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="p in sonarrMeta.profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </label>
                        <label>
                            <span>Root folder</span>
                            <select v-model="form.sonarr.defaults.rootFolderPath">
                                <option
                                    v-if="form.sonarr.defaults.rootFolderPath"
                                    :value="form.sonarr.defaults.rootFolderPath"
                                >
                                    {{ form.sonarr.defaults.rootFolderPath }}
                                </option>
                                <option :value="null" disabled>Select after Test Connection</option>
                                <option v-for="f in sonarrMeta.folders" :key="f.path" :value="f.path">{{ f.path }}</option>
                            </select>
                        </label>
                    </div>
                </template>
                <p v-else class="text-sm text-gray-400">Enable Sonarr to request and grab TV series.</p>
            </section>

            <section class="panel space-y-4 p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-gray-100">qBittorrent</h2>
                    <button
                        type="button"
                        class="btn btn-default btn-sm"
                        :disabled="testing === 'qbittorrent'"
                        @click="testService('qbittorrent')"
                    >
                        <LoaderCircle v-if="testing === 'qbittorrent'" class="mr-2 h-4 w-4 animate-spin" />
                        Test Connection
                    </button>
                </div>
                <label>
                    <span>Base URL</span>
                    <input v-model="form.qbittorrent.base_url" type="url" placeholder="http://qbittorrent:8080" />
                </label>
                <label>
                    <span>API key <span class="label-tip">leave blank to keep</span></span>
                    <input
                        v-model="form.qbittorrent.api_key"
                        type="password"
                        :placeholder="configs.qbittorrent?.has_api_key ? '••••••••' : 'qbt_...'"
                        autocomplete="off"
                    />
                </label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label>
                        <span>Username <span class="label-tip">if no API key</span></span>
                        <input v-model="form.qbittorrent.username" type="text" autocomplete="username" />
                    </label>
                    <label>
                        <span>Password <span class="label-tip">leave blank to keep</span></span>
                        <input
                            v-model="form.qbittorrent.password"
                            type="password"
                            :placeholder="configs.qbittorrent?.has_password ? '••••••••' : ''"
                            autocomplete="current-password"
                        />
                    </label>
                </div>
            </section>

            <div class="flex justify-end">
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="saving"
                    @click="save"
                >
                    <LoaderCircle v-if="saving" class="mr-2 h-4 w-4 animate-spin" />
                    <Save v-else class="mr-2 h-4 w-4" />
                    {{ saving ? 'Saving…' : 'Save settings' }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
