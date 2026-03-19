<script setup>
import { RouterLink } from 'vue-router'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <nav class="bg-white border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-14">
          <div class="flex items-center gap-6">
            <RouterLink
              to="/"
              class="text-lg font-semibold text-gray-900 hover:text-gray-600"
            >
              MovieMind
            </RouterLink>
            <RouterLink
              to="/search"
              class="text-gray-600 hover:text-gray-900"
            >
              {{ $t('nav.search') }}
            </RouterLink>
            <RouterLink
              to="/compare"
              class="text-gray-600 hover:text-gray-900"
            >
              {{ $t('nav.compare') }}
            </RouterLink>
            <RouterLink
              to="/generate"
              class="text-gray-600 hover:text-gray-900"
            >
              {{ $t('nav.generate') }}
            </RouterLink>
            <RouterLink
              to="/feedback"
              class="text-gray-600 hover:text-gray-900"
            >
              {{ $t('nav.feedback') }}
            </RouterLink>
          </div>
          <div class="flex items-center">
            <LanguageSwitcher />
          </div>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
      <RouterView />
    </main>

    <!-- Global footer: attribution as required by data source licenses -->
    <footer class="bg-white border-t border-gray-100 mt-auto">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-wrap items-center justify-center gap-6">

        <!-- TMDb: logo + text + link required -->
        <a
          href="https://www.themoviedb.org"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-2 group opacity-60 hover:opacity-100 transition-opacity"
          title="Movie and TV data provided by The Movie Database (TMDb)"
        >
          <img
            src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg"
            alt="The Movie Database (TMDb)"
            class="h-4"
          />
        </a>

        <span class="text-gray-200 select-none text-xs">·</span>

        <!-- TVmaze: link required (CC BY-SA) -->
        <a
          href="https://www.tvmaze.com"
          target="_blank"
          rel="noopener noreferrer"
          class="text-xs text-gray-400 hover:text-gray-600 transition-colors"
          title="TV show data provided by TVmaze – licensed under CC BY-SA"
        >
          TVmaze <span class="text-gray-300">CC BY-SA</span>
        </a>

        <span class="text-gray-200 select-none text-xs">·</span>

        <span class="text-xs text-gray-300">
          {{ $t('footer.tmdb_disclaimer') }}
        </span>
      </div>
    </footer>
  </div>
</template>
