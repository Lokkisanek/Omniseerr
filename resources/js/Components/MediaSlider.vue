<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import TitleCard from './TitleCard.vue';

defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    href: { type: String, default: null },
});

const emit = defineEmits(['grab']);
const scroller = ref(null);

function scrollBy(dir) {
    const el = scroller.value;
    if (!el) return;
    const amount = Math.max(320, Math.floor(el.clientWidth * 0.9));
    el.scrollBy({ left: dir * amount, behavior: 'smooth' });
}
</script>

<template>
    <section v-if="items.length" class="media-slider">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-2">
                <h2 class="truncate text-xl font-bold text-gray-100 sm:text-2xl">{{ title }}</h2>
                <Link
                    v-if="href"
                    :href="href"
                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-700 text-gray-200 transition hover:bg-gray-600"
                    :title="`View all ${title}`"
                >
                    <ChevronRight class="h-4 w-4" />
                </Link>
            </div>
            <div class="hidden items-center gap-1 text-gray-500 sm:flex">
                <button type="button" class="rounded p-1 hover:bg-gray-800 hover:text-white" aria-label="Scroll left" @click="scrollBy(-1)">
                    <ChevronLeft class="h-5 w-5" />
                </button>
                <button type="button" class="rounded p-1 hover:bg-gray-800 hover:text-white" aria-label="Scroll right" @click="scrollBy(1)">
                    <ChevronRight class="h-5 w-5" />
                </button>
            </div>
        </div>

        <div
            ref="scroller"
            class="hide-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 pb-3 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        >
            <div
                v-for="item in items"
                :key="`${item.mediaType}-${item.tmdbId}`"
                class="w-[8.75rem] shrink-0 sm:w-[9.75rem] md:w-[10.5rem]"
            >
                <TitleCard :item="item" @grab="emit('grab', $event)" />
            </div>
        </div>
    </section>
</template>
