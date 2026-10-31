<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import PlexOAuth from '../../utils/plex';

const props = defineProps({
    canRegister: { type: Boolean, default: false },
    setupComplete: { type: Boolean, default: true },
    hasUsers: { type: Boolean, default: true },
    plexClientId: { type: String, required: true },
    plexEnabled: { type: Boolean, default: true },
    localLogin: { type: Boolean, default: true },
    applicationTitle: { type: String, default: 'Omniseerr' },
});

const showLocal = ref(!props.plexEnabled && props.localLogin);
const plexLoading = ref(false);
const plexError = ref('');

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

function submitLocal() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}

async function signInWithPlex() {
    plexError.value = '';
    plexLoading.value = true;

    const oauth = new PlexOAuth();
    // Open popup synchronously inside the click handler (browser popup blockers).
    oauth.preparePopup();

    try {
        const authToken = await oauth.login(props.plexClientId);
        router.post('/auth/plex', { authToken }, {
            onError: (errors) => {
                plexError.value = errors.plex || errors.authToken || 'Plex login failed.';
                plexLoading.value = false;
            },
            onFinish: () => {
                plexLoading.value = false;
            },
        });
    } catch (e) {
        plexError.value = e.response?.data?.message || e.message || 'Plex login failed.';
        plexLoading.value = false;
    }
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-gray-900 px-4">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold tracking-tight">
                    <span class="text-brand">Omni</span><span class="text-white">seerr</span>
                </h1>
                <p class="description mx-auto mt-2">Sign in to continue</p>
            </div>

            <div class="panel space-y-5 p-6">
                <button
                    v-if="plexEnabled"
                    type="button"
                    class="plex-button flex w-full items-center justify-center rounded-md px-4 py-2.5 text-sm font-medium text-white transition disabled:opacity-50"
                    :disabled="plexLoading"
                    @click="signInWithPlex"
                >
                    <svg class="mr-2 h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M4 2h16v20H4z" opacity=".15" />
                        <path d="M8.5 3.5h3.2L15.5 12l-3.8 8.5H8.5L12.3 12 8.5 3.5z" />
                    </svg>
                    {{ plexLoading ? 'Waiting for Plex…' : 'Sign in with Plex' }}
                </button>

                <div v-if="plexError" class="form-error">{{ plexError }}</div>

                <template v-if="localLogin">
                <div v-if="plexEnabled" class="relative py-1 text-center text-xs font-semibold tracking-wider text-gray-500 uppercase">
                    <span class="bg-gray-800 px-2">or</span>
                    <div class="absolute inset-x-0 top-1/2 -z-10 border-t border-gray-700" />
                </div>

                <button
                    v-if="plexEnabled"
                    type="button"
                    class="btn btn-default w-full"
                    @click="showLocal = !showLocal"
                >
                    {{ showLocal ? 'Hide local login' : 'Use local email / password' }}
                </button>

                <form v-if="showLocal || !plexEnabled" class="space-y-4 border-t border-gray-700 pt-4" @submit.prevent="submitLocal">
                    <label>
                        Email address
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            class="mt-1"
                        />
                        <div v-if="form.errors.email" class="error">{{ form.errors.email }}</div>
                    </label>

                    <label>
                        Password
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="mt-1"
                        />
                    </label>

                    <label class="mb-0 flex items-center gap-2 font-medium text-gray-300">
                        <input v-model="form.remember" type="checkbox" />
                        Remember me
                    </label>

                    <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>
                </template>
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
