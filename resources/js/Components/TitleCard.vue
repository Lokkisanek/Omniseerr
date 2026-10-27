<script setup>
import { computed, ref } from 'vue';
import { CheckCircle2, Clock3, Download, LoaderCircle } from '@lucide/vue';

const props = defineProps({
    item: { type: Object, required: true },
});

const emit = defineEmits(['grab']);

const hovered = ref(false);

const mediaLabel = computed(() => (props.item.mediaType === 'tv' ? 'TV' : 'MOVIE'));
const mediaBadgeClass = computed(() =>
    props.item.mediaType === 'tv'
        ? 'border-purple-600 bg-purple-600/80'
        : 'border-blue-500 bg-blue-600/80',
);

const status = computed(() => props.item.libraryStatus || 'UNREQUESTED');

const statusMeta = computed(() => {
    switch (status.value) {
        case 'AVAILABLE':
            return { class: 'bg-green-500/80 border-green-400 text-green-100', icon: CheckCircle2 };
        case 'DOWNLOADING':
            return { class: 'bg-indigo-500/80 border-indigo-400 text-indigo-100 animate-pulse', icon: LoaderCircle };
        case 'MONITORED':
            return { class: 'bg-yellow-500/80 border-yellow-400 text-yellow-100', icon: Clock3 };
        default:
            return null;
    }
});
</script>

<template>
    <div class="w-full" data-testid="title-card">
        <div
            class="relative transform-gpu cursor-default overflow-hidden rounded-xl bg-gray-800 bg-cover outline-none ring-1 transition duration-300"
            :class="hovered ? 'scale-105 shadow-lg ring-gray-500' : 'scale-100 shadow ring-gray-700'"
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
                />
                <div
                    v-else
                    class="absolute inset-0 flex items-center justify-center bg-gray-800 text-xs font-semibold tracking-wide text-gray-500 uppercase"
                >
                    No poster
                </div>

                <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-transparent to-transparent opacity-90" />

                <div class="absolute inset-x-0 top-0 flex items-start justify-between p-2">
                    <div class="pointer-events-none z-40 rounded-full border shadow-md" :class="mediaBadgeClass">
                        <div class="flex h-4 items-center px-2 text-center text-[10px] font-medium tracking-wider text-white uppercase sm:h-5 sm:text-xs">
                            {{ mediaLabel }}
                        </div>
                    </div>

                    <div
                        v-if="statusMeta"
                        class="z-40 flex h-5 w-5 items-center justify-center rounded-full border p-0.5 shadow-md ring-1"
                        :class="statusMeta.class"
                        :title="status"
                    >
                        <component :is="statusMeta.icon" class="h-3.5 w-3.5" :class="status === 'DOWNLOADING' ? 'animate-spin' : ''" />
                    </div>
                </div>

                <div class="absolute inset-x-0 bottom-0 space-y-2 p-2.5">
                    <div>
                        <div v-if="item.year" class="text-[11px] font-medium text-gray-300">{{ item.year }}</div>
                        <div class="line-clamp-2 text-sm leading-snug font-bold text-white">{{ item.title }}</div>
                    </div>

                    <button
                        v-if="item.mediaType === 'movie'"
                        type="button"
                        class="btn btn-primary btn-sm z-40 w-full"
                        @click.stop="emit('grab', item)"
                    >
                        <Download class="mr-1.5 h-3.5 w-3.5" />
                        Grab
                    </button>
                    <div
                        v-else
                        class="rounded-md border border-gray-600 bg-gray-900/70 px-2 py-1.5 text-center text-[11px] text-gray-400"
                    >
                        TV grab soon
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
