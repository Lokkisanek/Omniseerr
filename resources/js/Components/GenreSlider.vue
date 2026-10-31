<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    mediaType: { type: String, default: 'movie' },
});

const scroller = ref(null);

function scrollBy(dir) {
    const el = scroller.value;
    if (!el) return;
    el.scrollBy({ left: dir * Math.max(320, el.clientWidth * 0.8), behavior: 'smooth' });
}

function hrefFor(genre) {
    return `/discover?type=${props.mediaType}&genre=${genre.id}`;
}
</script>

<template>
    <section v-if="items.length" class="genre-slider">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-gray-100 sm:text-2xl">{{ title }}</h2>
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-700 text-gray-300">
                    <ChevronRight class="h-4 w-4" />
                </span>
            </div>
            <div class="hidden items-center gap-1 text-gray-500 sm:flex">
                <button type="button" class="rounded p-1 hover:bg-gray-800 hover:text-white" @click="scrollBy(-1)">
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <button type="button" class="rounded p-1 hover:bg-gray-800 hover:text-white" @click="scrollBy(1)">
                    <ChevronRight class="h-5 w-5" />
                </button>
            </div>
        </div>

        <div
            ref="scroller"
            class="hide-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 pb-3 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        >
            <Link
                v-for="genre in items"
                :key="genre.id"
                :href="hrefFor(genre)"
                class="relative flex h-28 w-48 shrink-0 items-center justify-center overflow-hidden rounded-xl shadow-md ring-1 ring-black/20 transition hover:scale-[1.03] sm:h-32 sm:w-56"
                :style="{ background: `linear-gradient(135deg, ${genre.color}ee, ${genre.color}99)` }"
            >
                <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 30% 20%, #fff6, transparent 55%)" />
                <span class="relative z-10 px-3 text-center text-lg font-bold text-white drop-shadow">
                    {{ genre.name }}
                </span>
            </Link>
        </div>
    </section>
</template>
