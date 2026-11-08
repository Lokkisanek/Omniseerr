<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, User } from '@lucide/vue';
import { ref } from 'vue';

defineProps({
    title: { type: String, default: 'Cast' },
    items: { type: Array, default: () => [] },
});

const scroller = ref(null);

function scrollBy(dir) {
    const el = scroller.value;
    if (!el) return;
    el.scrollBy({ left: dir * Math.max(280, el.clientWidth * 0.8), behavior: 'smooth' });
}
</script>

<template>
    <section v-if="items.length" class="cast-slider">
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
            class="hide-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        >
            <Link
                v-for="person in items"
                :key="person.id"
                :href="`/person/${person.id}`"
                class="w-28 shrink-0 text-center transition hover:opacity-90 sm:w-32"
            >
                <div class="mx-auto mb-2 flex h-28 w-28 items-center justify-center overflow-hidden rounded-full bg-gray-800 ring-1 ring-gray-700 sm:h-32 sm:w-32">
                    <img
                        v-if="person.profileUrl"
                        :src="person.profileUrl"
                        :alt="person.name"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                    <User v-else class="h-10 w-10 text-gray-500" />
                </div>
                <div class="truncate text-sm font-semibold text-white">{{ person.name }}</div>
                <div class="truncate text-xs text-gray-400">{{ person.character }}</div>
            </Link>
        </div>
    </section>
</template>
