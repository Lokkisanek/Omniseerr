<script setup>
import { ref } from 'vue';
import { ExternalLink, User } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import InteractiveGrabModal from '../../Components/InteractiveGrabModal.vue';
import TitleCard from '../../Components/TitleCard.vue';

defineProps({
    person: { type: Object, required: true },
    movies: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
});

const selected = ref(null);
</script>

<template>
    <AppLayout :title="person.name">
        <div class="section !mt-2">
            <div class="mb-10 flex flex-col gap-6 sm:flex-row">
                <div class="mx-auto h-48 w-48 shrink-0 overflow-hidden rounded-full bg-gray-800 ring-1 ring-gray-700 sm:mx-0 sm:h-56 sm:w-56">
                    <img
                        v-if="person.profileUrl"
                        :src="person.profileUrl"
                        :alt="person.name"
                        class="h-full w-full object-cover"
                    />
                    <div v-else class="flex h-full w-full items-center justify-center">
                        <User class="h-16 w-16 text-gray-500" />
                    </div>
                </div>

                <div class="min-w-0 flex-1">
                    <h1 class="text-3xl font-extrabold text-white sm:text-4xl">{{ person.name }}</h1>
                    <p class="mt-2 text-sm text-gray-400">
                        <span v-if="person.knownFor">{{ person.knownFor }}</span>
                        <span v-if="person.birthday"> · Born {{ person.birthday }}</span>
                        <span v-if="person.placeOfBirth"> · {{ person.placeOfBirth }}</span>
                    </p>

                    <a
                        v-if="person.imdbId"
                        :href="`https://www.imdb.com/name/${person.imdbId}`"
                        target="_blank"
                        rel="noopener"
                        class="btn btn-default btn-sm mt-4"
                    >
                        <ExternalLink class="mr-1.5 h-4 w-4" />
                        IMDb
                    </a>

                    <p
                        v-if="person.biography"
                        class="mt-5 max-w-3xl text-sm leading-6 whitespace-pre-line text-gray-300"
                    >
                        {{ person.biography }}
                    </p>
                </div>
            </div>

            <div class="space-y-10">
                <section v-if="movies.length">
                    <h2 class="mb-4 text-2xl font-bold text-white">Movies</h2>
                    <ul class="cards-vertical cards-dense">
                        <li v-for="item in movies" :key="`m-${item.tmdbId}`">
                            <TitleCard :item="item" @grab="selected = $event" />
                        </li>
                    </ul>
                </section>

                <section v-if="series.length">
                    <h2 class="mb-4 text-2xl font-bold text-white">Series</h2>
                    <ul class="cards-vertical cards-dense">
                        <li v-for="item in series" :key="`t-${item.tmdbId}`">
                            <TitleCard :item="item" />
                        </li>
                    </ul>
                </section>

                <div
                    v-if="!movies.length && !series.length"
                    class="panel border-dashed px-6 py-16 text-center text-sm text-gray-400"
                >
                    No filmography found.
                </div>
            </div>
        </div>

        <InteractiveGrabModal
            v-if="selected"
            :media="selected"
            :profiles="[]"
            :folders="[]"
            :defaults="{}"
            @close="selected = null"
            @grabbed="selected = null"
        />
    </AppLayout>
</template>
