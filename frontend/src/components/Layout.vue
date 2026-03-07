<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-gray-50">
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
              Search
            </RouterLink>
            <RouterLink
              to="/compare"
              class="text-gray-600 hover:text-gray-900"
            >
              Compare
            </RouterLink>
            <RouterLink
              to="/generate"
              class="text-gray-600 hover:text-gray-900"
            >
              Generate
            </RouterLink>
            <RouterLink
              to="/feedback"
              class="text-gray-600 hover:text-gray-900"
            >
              Feedback
            </RouterLink>
          </div>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <RouterView />
    </main>
  </div>
</template>
