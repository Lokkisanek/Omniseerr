<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock3, Download, LoaderCircle } from '@lucide/vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const emit = defineEmits(['grab']);

const page = usePage();
const canGrab = computed(() => {
    const can = page.props.auth?.user?.can || {};
    return props.item.mediaType === 'tv' ? !!can.grabTv : !!can.grabMovie;
});

const hovered = ref(false);

const mediaLabel = computed(() => (props.item.mediaType === 'tv' ? 'SERIES' : 'MOVIE'));
const mediaBadgeClass = computed(() =>
    props.item.mediaType === 'tv'
        ? 'border-purple-500 bg-purple-600'
        : 'border-blue-400 bg-blue-600',
);

const status = computed(() => props.item.libraryStatus || 'UNREQUESTED');

const statusMeta = computed(() => {
    switch (status.value) {
        case 'AVAILABLE':
            return { class: 'bg-green-500 border-green-400 text-white', icon: CheckCircle2 };
        case 'DOWNLOADING':
        case 'PROCESSING':
            return { class: 'bg-indigo-500 border-indigo-400 text-white animate-pulse', icon: LoaderCircle };
        case 'MONITORED':
        case 'PENDING':
        case 'PARTIALLY_AVAILABLE':
            return { class: 'bg-yellow-500 border-yellow-400 text-white', icon: Clock3 };
        default:
            return null;
    }
});

const showGrab = computed(() => props.item.mediaType === 'movie' && canGrab.value && status.value !== 'AVAILABLE');
</script>

<template>
    <div class="w-full" data-testid="title-card">
        <div
            class="group relative transform-gpu overflow-hidden rounded-xl bg-gray-800 outline-none ring-1 transition duration-300 ease-out"
            :class="hovered ? 'z-20 scale-105 shadow-xl ring-gray-500' : 'scale-100 shadow ring-gray-700'"
            style="padding-bottom: 150%"
            @mouseenter="hovered = true"
            @mouseleave="hovered = false"
        >
            <div class="absolute inset-0 h-full w-full overflow-hidden">
                <img
                    v-if="item.posterUrl"
                    :src="item.posterUrl"
                    :alt="item.title"
                    class="absolute inset-0 h-full w-full object-cover"
                    loading="lazy"
                />
                <div
                    v-else
                    class="absolute inset-0 flex items-center justify-center bg-gray-800 text-xs font-semibold tracking-wide text-gray-500 uppercase"
                >
                    No poster
                </div>

                <!-- Always-visible badges -->
                <div class="absolute inset-x-0 top-0 z-30 flex items-start justify-between p-2">
                    <div class="rounded-full border shadow-md" :class="mediaBadgeClass">
                        <div class="flex h-4 items-center px-2 text-[10px] font-semibold tracking-wider text-white uppercase sm:h-5 sm:text-xs">
                            {{ mediaLabel }}
                        </div>
                    </div>
                    <div
                        v-if="statusMeta"
                        class="flex h-5 w-5 items-center justify-center rounded-full border shadow-md"
                        :class="statusMeta.class"
                        :title="status"
                    >
                        <component
                            :is="statusMeta.icon"
                            class="h-3.5 w-3.5"
                            :class="status === 'DOWNLOADING' || status === 'PROCESSING' ? 'animate-spin' : ''"
                        />
                    </div>
                </div>

                <!-- Hover overlay (Seerr-style) -->
                <div
                    class="absolute inset-0 z-20 flex flex-col justify-end bg-gradient-to-t from-gray-900 via-gray-900/80 to-gray-900/20 p-2.5 transition duration-200"
                    :class="hovered ? 'opacity-100' : 'opacity-0'"
                >
                    <div v-if="item.year" class="text-[11px] font-medium text-gray-300">{{ item.year }}</div>
                    <div class="line-clamp-2 text-sm leading-snug font-bold text-white">{{ item.title }}</div>
                    <p v-if="item.overview" class="mt-1 line-clamp-3 text-[11px] leading-snug text-gray-300">
                        {{ item.overview }}
                    </p>

                    <button
                        v-if="showGrab"
                        type="button"
                        class="btn btn-primary btn-sm mt-2 w-full"
                        @click.stop="emit('grab', item)"
                    >
                        <Download class="mr-1.5 h-3.5 w-3.5" />
                        Request
                    </button>
                    <div
                        v-else-if="status === 'AVAILABLE'"
                        class="mt-2 rounded-md border border-green-500/40 bg-green-500/10 px-2 py-1.5 text-center text-[11px] font-medium text-green-300"
                    >
                        Available
                    </div>
                    <div
                        v-else-if="item.mediaType === 'tv'"
                        class="mt-2 rounded-md border border-gray-600 bg-gray-900/70 px-2 py-1.5 text-center text-[11px] text-gray-400"
                    >
                        TV grab soon
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
