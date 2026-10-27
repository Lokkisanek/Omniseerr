<script setup>
import { Link } from '@inertiajs/vue3';
import { Activity, Clapperboard, Settings } from '@lucide/vue';
import LiveDownloadDock from '../Components/LiveDownloadDock.vue';
import { useDownloadsLifecycle } from '../Stores/downloads';

useDownloadsLifecycle();

defineProps({
    title: { type: String, default: 'Omniseerr' },
});
</script>

<template>
    <div class="min-h-screen pb-28">
        <header class="sticky top-0 z-40 border-b border-white/10 bg-[#090d16]/80 backdrop-blur-xl">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <Link href="/discover" class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-500/20 text-blue-300 ring-1 ring-blue-400/30">
                        <Clapperboard class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="text-lg font-semibold tracking-tight text-white">Omniseerr</div>
                        <div class="text-[11px] uppercase tracking-[0.18em] text-slate-500">Interactive media desk</div>
                    </div>
                </Link>

                <nav class="flex items-center gap-2 text-sm">
                    <Link
                        href="/discover"
                        class="rounded-lg px-3 py-2 text-slate-300 transition hover:bg-white/5 hover:text-white"
                    >
                        Discover
                    </Link>
                    <Link
                        href="/activity"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-slate-300 transition hover:bg-white/5 hover:text-white"
                    >
                        <Activity class="h-4 w-4" />
                        Activity
                    </Link>
                    <Link
                        href="/setup?force=1"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
                    >
                        <Settings class="h-4 w-4" />
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            <slot />
        </main>

        <LiveDownloadDock />
    </div>
</template>
