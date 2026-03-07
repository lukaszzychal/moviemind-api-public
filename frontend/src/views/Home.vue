<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const query = ref('')

function goSearch () {
  const q = query.value?.trim()
  if (q) {
    router.push({ name: 'Search', query: { q, type: 'movies' } })
  } else {
    router.push({ name: 'Search' })
  }
}
</script>

<template>
  <div class="text-center max-w-2xl mx-auto">
    <h1 class="text-4xl font-bold text-gray-900">
      MovieMind
    </h1>
    <p class="mt-2 text-gray-600">
      AI-powered descriptions for movies, people, and TV. Search and explore.
    </p>
    <form
      class="mt-6 flex gap-2"
      @submit.prevent="goSearch"
    >
      <input
        v-model="query"
        type="search"
        placeholder="Search movies, people, series..."
        class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
      >
      <button
        type="submit"
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
      >
        Search
      </button>
    </form>
    <div class="mt-8 flex flex-wrap justify-center gap-4">
      <router-link
        to="/search?type=movies"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        Movies
      </router-link>
      <router-link
        to="/search?type=people"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        People
      </router-link>
      <router-link
        to="/search?type=tv-series"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        TV Series
      </router-link>
      <router-link
        to="/search?type=tv-shows"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        TV Shows
      </router-link>
    </div>
  </div>
</template>
