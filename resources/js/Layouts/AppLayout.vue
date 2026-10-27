<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Clapperboard,
    Film,
    Menu,
    Search,
    Settings,
    Sparkles,
    Tv,
    X,
} from '@lucide/vue';
import LiveDownloadDock from '../Components/LiveDownloadDock.vue';
import { useDownloadsLifecycle, useDownloadsStore } from '../Stores/downloads';

useDownloadsLifecycle();

defineProps({
    title: { type: String, default: 'Omniseerr' },
});

const page = usePage();
const downloads = useDownloadsStore();
const sidebarOpen = ref(false);
const scrolled = ref(false);
const searchQuery = ref('');

const path = computed(() => page.url.split('?')[0]);
const query = computed(() => {
    const raw = page.url.includes('?') ? page.url.split('?')[1] : '';
    return Object.fromEntries(new URLSearchParams(raw).entries());
});

const mediaType = computed(() => query.value.type || 'movie');

const links = computed(() => {
    const type = query.value.type;
    return [
        {
            href: '/discover',
            label: 'Discover',
            icon: Sparkles,
            active: path.value === '/discover' && !type,
        },
        {
            href: '/discover?type=movie',
            label: 'Movies',
            icon: Film,
            active: path.value === '/discover' && type === 'movie',
        },
        {
            href: '/discover?type=tv',
            label: 'Series',
            icon: Tv,
            active: path.value === '/discover' && type === 'tv',
        },
        {
            href: '/activity',
            label: 'Activity',
            icon: Activity,
            active: path.value === '/activity',
            badge: downloads.activeCount || null,
        },
        {
            href: '/setup?force=1',
            label: 'Settings',
            icon: Settings,
            active: path.value === '/setup',
        },
    ];
});

function submitSearch() {
    router.get(
        '/discover',
        { q: searchQuery.value, type: mediaType.value },
        { preserveState: true, replace: true },
    );
}

function onScroll() {
    scrolled.value = window.pageYOffset > 20;
}

onMounted(() => {
    searchQuery.value = query.value.q || '';
    window.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <div class="flex min-h-screen bg-gray-900">
        <aside class="sidebar fixed inset-y-0 left-0 z-40 hidden w-64 flex-col lg:flex">
            <div class="flex h-16 items-center px-4">
                <Link href="/discover" class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600/30 text-indigo-300 ring-1 ring-indigo-500/40">
                        <Clapperboard class="h-5 w-5" />
                    </div>
                    <span class="text-lg font-bold tracking-tight">
                        <span class="text-brand">Omni</span><span class="text-white">seerr</span>
                    </span>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="flex items-center rounded-md px-3 py-2 text-base leading-6 font-medium text-white transition duration-150 ease-in-out focus:outline-none"
                    :class="link.active ? 'nav-active' : 'hover:bg-gray-700 focus:bg-gray-700'"
                >
                    <component :is="link.icon" class="mr-3 h-6 w-6 shrink-0" />
                    <span class="flex-1">{{ link.label }}</span>
                    <span
                        v-if="link.badge"
                        class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-indigo-500 px-1.5 py-0.5 text-xs font-semibold text-white"
                    >
                        {{ link.badge }}
                    </span>
                </Link>
            </nav>
        </aside>

        <div v-if="sidebarOpen" class="fixed inset-0 z-50 lg:hidden">
            <div class="absolute inset-0 bg-gray-900/80" @click="sidebarOpen = false" />
            <aside class="sidebar absolute inset-y-0 left-0 flex w-64 flex-col shadow-xl">
                <div class="flex h-16 items-center justify-between px-4">
                    <span class="text-lg font-bold">
                        <span class="text-brand">Omni</span><span class="text-white">seerr</span>
                    </span>
                    <button type="button" class="btn btn-ghost btn-sm" @click="sidebarOpen = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <nav class="flex-1 space-y-1 px-3 py-4">
                    <Link
                        v-for="link in links"
                        :key="`m-${link.href}`"
                        :href="link.href"
                        class="flex items-center rounded-md px-3 py-2 text-base font-medium text-white transition duration-150"
                        :class="link.active ? 'nav-active' : 'hover:bg-gray-700'"
                        @click="sidebarOpen = false"
                    >
                        <component :is="link.icon" class="mr-3 h-6 w-6" />
                        {{ link.label }}
                    </Link>
                </nav>
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-64">
            <header
                class="searchbar sticky top-0 z-30 flex items-center gap-3 border-b border-transparent px-4 transition duration-300 sm:px-6 lg:px-8"
                :class="scrolled ? 'border-gray-700 bg-gray-900/80 backdrop-blur' : 'bg-transparent'"
            >
                <button type="button" class="btn btn-ghost btn-sm lg:hidden" @click="sidebarOpen = true">
                    <Menu class="h-5 w-5" />
                </button>

                <form class="relative mx-auto w-full max-w-xl" @submit.prevent="submitSearch">
                    <Search class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-gray-500" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search movies & TV..."
                        class="!rounded-full !border-gray-600 !bg-gray-800 py-2 pr-4 pl-10"
                    />
                </form>
            </header>

            <main class="relative flex-1 px-4 pt-6 pb-28 sm:px-6 lg:px-8">
                <slot />
            </main>

            <LiveDownloadDock />
        </div>
    </div>
</template>
