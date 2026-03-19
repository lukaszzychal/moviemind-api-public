<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import Input from '@/components/ui/Input.vue'
import Button from '@/components/ui/Button.vue'

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
      {{ $t('home.subtitle') }}
    </p>
    <form
      class="mt-8 flex flex-col sm:flex-row gap-3"
      @submit.prevent="goSearch"
    >
      <div class="flex-1">
        <Input
          v-model="query"
          type="search"
          :placeholder="$t('home.search_placeholder')"
          hide-details
        />
      </div>
      <Button
        type="submit"
        variant="primary"
        class="w-full sm:w-auto"
      >
        {{ $t('home.search_button') }}
      </Button>
    </form>
    <div class="mt-8 flex flex-wrap justify-center gap-4">
      <router-link
        to="/search?type=movies"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        {{ $t('types.movies') }}
      </router-link>
      <router-link
        to="/search?type=people"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        {{ $t('types.people') }}
      </router-link>
      <router-link
        to="/search?type=tv-series"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        {{ $t('types.tv_series') }}
      </router-link>
      <router-link
        to="/search?type=tv-shows"
        class="text-indigo-600 hover:text-indigo-800 font-medium"
      >
        {{ $t('types.tv_shows') }}
      </router-link>
    </div>

    <!-- Data Sources & Attribution -->
    <div class="mt-12 border-t border-gray-200 pt-8">
      <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase mb-4">{{ $t('home.data_sources') }}</p>
      <div class="flex flex-wrap items-center justify-center gap-6">

        <!-- TMDb attribution (required: logo + text + link) -->
        <a
          href="https://www.themoviedb.org"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-2 group"
          title="Movie and TV data provided by The Movie Database (TMDb)"
        >
          <img
            src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg"
            alt="The Movie Database (TMDb) logo"
            class="h-5 opacity-70 group-hover:opacity-100 transition-opacity"
          />
          <span class="text-xs text-gray-400 group-hover:text-gray-600 transition-colors">
            Movie &amp; TV data
          </span>
        </a>

        <span class="text-gray-200 select-none">|</span>

        <!-- TVmaze attribution (required: link to tvmaze.com, CC BY-SA) -->
        <a
          href="https://www.tvmaze.com"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-2 group"
          title="TV show data provided by TVmaze (CC BY-SA)"
        >
          <svg class="h-4 w-4 text-gray-400 group-hover:text-indigo-500 transition-colors" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-9 3l4 9h-2.5l-.75-1.75h-3.5L8.5 15H6l4-9h2zm-.75 5.25h1.5L12 8.75l-.75 2.5z"/>
          </svg>
          <span class="text-xs text-gray-400 group-hover:text-gray-600 transition-colors">
            TVmaze
            <span class="text-gray-300 ml-1">CC BY-SA</span>
          </span>
        </a>

      </div>
      <p class="mt-3 text-xs text-gray-300">
        This product uses the TMDB API but is not endorsed or certified by TMDB.
      </p>
    </div>
  </div>
</template>
