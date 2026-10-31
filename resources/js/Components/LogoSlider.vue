<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    /** studios | networks */
    kind: { type: String, default: 'networks' },
});

const scroller = ref(null);

const links = computed(() =>
    props.items.map((item) => ({
        ...item,
        href: props.kind === 'studios'
            ? `/discover?type=movie&studio=${item.id}`
            : `/discover?type=movie&provider=${item.id}`,
        label: item.name,
        image: item.logoUrl || null,
    })),
);

function scrollBy(dir) {
    const el = scroller.value;
    if (!el) return;
    el.scrollBy({ left: dir * Math.max(320, el.clientWidth * 0.8), behavior: 'smooth' });
}
</script>

<template>
    <section v-if="links.length" class="logo-slider">
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
                v-for="item in links"
                :key="`${kind}-${item.id}`"
                :href="item.href"
                class="flex h-28 w-48 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-gray-800 to-gray-900 px-4 shadow-md ring-1 ring-gray-700 transition hover:scale-[1.03] hover:ring-gray-500 sm:h-32 sm:w-56"
            >
                <img
                    v-if="item.image"
                    :src="item.image"
                    :alt="item.label"
                    class="max-h-14 max-w-full object-contain"
                />
                <span
                    v-else
                    class="text-center text-sm font-semibold tracking-wide text-white uppercase"
                >
                    {{ item.label }}
                </span>
            </Link>
        </div>
    </section>
</template>
