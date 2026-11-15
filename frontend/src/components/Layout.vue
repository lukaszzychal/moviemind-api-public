<script setup>
import { ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import LanguageSwitcher from '@/components/LanguageSwitcher.vue'

const navLinks = [
  { to: '/search', key: 'nav.search' },
  { to: '/compare', key: 'nav.compare' },
  { to: '/generate', key: 'nav.generate' },
  { to: '/feedback', key: 'nav.feedback' },
]

const mobileMenuOpen = ref(false)
const route = useRoute()

// Close the mobile menu after navigating
watch(() => route.fullPath, () => {
  mobileMenuOpen.value = false
})
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
              v-for="link in navLinks"
              :key="link.to"
              :to="link.to"
              class="hidden sm:inline text-gray-600 hover:text-gray-900"
            >
              {{ $t(link.key) }}
            </RouterLink>
          </div>
          <div class="flex items-center gap-1">
            <LanguageSwitcher />
            <button
              type="button"
              class="sm:hidden inline-flex items-center justify-center p-2 rounded-lg text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
              :aria-expanded="mobileMenuOpen"
              aria-controls="mobile-menu"
              :aria-label="mobileMenuOpen ? $t('nav.close_menu') : $t('nav.open_menu')"
              @click="mobileMenuOpen = !mobileMenuOpen"
            >
              <svg
                class="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
              >
                <path
                  v-if="mobileMenuOpen"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M6 18L18 6M6 6l12 12"
                />
                <path
                  v-else
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M4 6h16M4 12h16M4 18h16"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
      <div
        v-show="mobileMenuOpen"
        id="mobile-menu"
        class="sm:hidden border-t border-gray-200"
      >
        <div class="px-4 py-2 flex flex-col">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="py-2 text-gray-600 hover:text-gray-900"
            active-class="text-gray-900 font-medium"
          >
            {{ $t(link.key) }}
          </RouterLink>
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

        <span class="text-gray-200 select-none text-xs">·</span>

        <div class="text-xs text-gray-500 flex flex-wrap items-center gap-3">
          <span class="font-semibold text-gray-600">Autor:</span>
          <span>Łukasz Zychal</span>
          <a class="text-blue-600 hover:underline" href="mailto:lukasz.zychal.dev@gmail.com">lukasz.zychal.dev@gmail.com</a>
          <a class="text-blue-600 hover:underline" href="https://www.linkedin.com/in/lukaszzychal/" target="_blank" rel="noopener noreferrer">LinkedIn</a>
          <a class="text-blue-600 hover:underline" href="https://github.com/lukaszzychal" target="_blank" rel="noopener noreferrer">GitHub</a>
        </div>
      </div>
    </footer>
  </div>
</template>
