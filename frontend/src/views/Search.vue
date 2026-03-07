<script setup>
import { ref, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  searchMovies,
  searchPeople,
  searchTvSeries,
  searchTvShows,
} from '@/api/client'

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const error = ref(null)
const result = ref(null)

const type = computed(() => route.query.type || 'movies')
const q = computed(() => route.query.q || '')
const page = computed(() => Math.max(1, parseInt(route.query.page, 10) || 1))

const typeOptions = [
  { value: 'movies', label: 'Movies' },
  { value: 'people', label: 'People' },
  { value: 'tv-series', label: 'TV Series' },
  { value: 'tv-shows', label: 'TV Shows' },
]

async function runSearch () {
  error.value = null
  result.value = null
  const params = { page: page.value, per_page: 20 }
  if (q.value) params.q = q.value

  loading.value = true
  try {
    switch (type.value) {
      case 'movies':
        result.value = await searchMovies(params)
        break
      case 'people':
        result.value = await searchPeople(params)
        break
      case 'tv-series':
        result.value = await searchTvSeries(params)
        break
      case 'tv-shows':
        result.value = await searchTvShows(params)
        break
      default:
        result.value = await searchMovies(params)
    }
  } catch (e) {
    error.value = e.data?.message || e.message || 'Search failed'
  } finally {
    loading.value = false
  }
}

watch([type, q, page], runSearch, { immediate: true })

function detailPath (item) {
  const slug = item.slug
  switch (type.value) {
    case 'movies':
      return { name: 'MovieDetail', params: { slug } }
    case 'people':
      return { name: 'PersonDetail', params: { slug } }
    case 'tv-series':
      return { name: 'TvSeriesDetail', params: { slug } }
    case 'tv-shows':
      return { name: 'TvShowDetail', params: { slug } }
    default:
      return { name: 'MovieDetail', params: { slug } }
  }
}

function itemTitle (item) {
  if (type.value === 'people') return item.name
  return item.title || item.name
}

function itemSubtitle (item) {
  if (type.value === 'movies' && item.release_year) return String(item.release_year)
  if (type.value === 'people' && item.birth_date) return item.birth_date
  if ((type.value === 'tv-series' || type.value === 'tv-shows') && item.first_air_date) return item.first_air_date
  return null
}

function goPage (newPage) {
  router.push({
    name: 'Search',
    query: { ...route.query, page: newPage },
  })
}

const items = computed(() => {
  const r = result.value
  if (!r) return []
  return r.data ?? r.results ?? []
})

const pagination = computed(() => result.value?.pagination ?? null)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      Search
    </h1>
    <form
      class="flex flex-wrap gap-2 mb-6"
      @submit.prevent="runSearch"
    >
      <input
        :value="q"
        type="search"
        placeholder="Query..."
        class="rounded-lg border border-gray-300 px-4 py-2 flex-1 min-w-[200px]"
        @input="(e) => router.replace({ query: { ...route.query, q: e.target.value, page: 1 } })"
      >
      <select
        :value="type"
        class="rounded-lg border border-gray-300 px-4 py-2"
        @change="(e) => router.replace({ query: { ...route.query, type: e.target.value, page: 1 } })"
      >
        <option
          v-for="opt in typeOptions"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
      <button
        type="submit"
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
      >
        Search
      </button>
    </form>

    <div
      v-if="error"
      class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ error }}
    </div>
    <div
      v-if="loading"
      class="text-gray-500"
    >
      Loading...
    </div>
    <div
      v-if="!loading && items.length === 0 && (q || result)"
      class="text-gray-500"
    >
      No results.
    </div>
    <ul
      v-else-if="!loading && items.length"
      class="space-y-2"
    >
      <li
        v-for="item in items"
        :key="item.slug || item.id"
      >
        <router-link
          :to="detailPath(item)"
          class="block p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
        >
          <span class="font-medium text-gray-900">{{ itemTitle(item) }}</span>
          <span
            v-if="itemSubtitle(item)"
            class="text-gray-500 ml-2"
          >{{ itemSubtitle(item) }}</span>
        </router-link>
      </li>
    </ul>

    <div
      v-if="pagination && (pagination.total_pages > 1 || pagination.page > 1)"
      class="mt-6 flex gap-2 items-center"
    >
      <button
        :disabled="page <= 1"
        class="px-3 py-1 rounded border border-gray-300 disabled:opacity-50"
        @click="goPage(page - 1)"
      >
        Previous
      </button>
      <span class="text-gray-600">
        Page {{ pagination.page ?? page }} of {{ pagination.total_pages ?? 1 }}
      </span>
      <button
        :disabled="page >= (pagination.total_pages ?? 1)"
        class="px-3 py-1 rounded border border-gray-300 disabled:opacity-50"
        @click="goPage(page + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>
