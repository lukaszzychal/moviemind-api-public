<script setup>
import { ref, watch, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  searchMovies,
  searchPeople,
  searchTvSeries,
  searchTvShows,
} from '@/api/client'
import Input from '@/components/ui/Input.vue'
import Select from '../components/ui/Select.vue'
import Button from '../components/ui/Button.vue'
import Card from '../components/ui/Card.vue'
import Badge from '../components/ui/Badge.vue'
import { useI18n } from 'vue-i18n'

const { t: translate } = useI18n()

const route = useRoute()
const router = useRouter()
const isLoading = ref(false)
const searchError = ref(null)
const searchResult = ref(null)

const type = computed(() => route.query.type || 'movies')
const activeSearchQuery = computed(() => route.query.q || '')
const currentPage = computed(() => Math.max(1, parseInt(route.query.page, 10) || 1))
const itemsPerPage = computed(() => parseInt(route.query.per_page, 10) || 20)

const localSearchQuery = ref(route.query.q || '')
const localType = ref(route.query.type || 'movies')
const localItemsPerPage = ref(itemsPerPage.value)

watch(() => route.query.q, (newVal) => { localSearchQuery.value = newVal || '' })
watch(() => route.query.type, (newVal) => { localType.value = newVal || 'movies' })
watch(() => route.query.per_page, (newVal) => { localItemsPerPage.value = parseInt(newVal, 10) || 20 })

const typeOptions = computed(() => [
  { value: 'movies', label: translate('types.movies') },
  { value: 'people', label: translate('types.people') },
  { value: 'tv-series', label: translate('types.tv_series') },
  { value: 'tv-shows', label: translate('types.tv_shows') },
])

const perPageOptions = [
  { value: 10, label: '10' },
  { value: 20, label: '20' },
  { value: 50, label: '50' },
  { value: 100, label: '100' },
]

function submitSearch () {
  router.push({ query: { ...route.query, q: localSearchQuery.value, type: localType.value, per_page: localItemsPerPage.value, page: 1 } })
}

let currentSearchId = 0

async function runSearch () {
  const searchId = ++currentSearchId

  searchError.value = null
  searchResult.value = null
  const params = { page: currentPage.value, per_page: itemsPerPage.value }
  if (activeSearchQuery.value) params.q = activeSearchQuery.value

  isLoading.value = true
  try {
    let apiResponse = null
    switch (type.value) {
      case 'movies':
        apiResponse = await searchMovies(params)
        break
      case 'people':
        apiResponse = await searchPeople(params)
        break
      case 'tv-series':
        apiResponse = await searchTvSeries(params)
        break
      case 'tv-shows':
        apiResponse = await searchTvShows(params)
        break
      default:
        apiResponse = await searchMovies(params)
    }
    
    if (searchId === currentSearchId) {
      searchResult.value = apiResponse
    }
  } catch (error) {
    if (searchId === currentSearchId) {
      searchError.value = error.data?.message || error.message || 'Search failed'
    }
  } finally {
    if (searchId === currentSearchId) {
      isLoading.value = false
    }
  }
}

watch([type, activeSearchQuery, currentPage, itemsPerPage], runSearch, { immediate: true })

function detailPath (item) {
  const slug = item.slug || item.suggested_slug
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
  if ((type.value === 'tv-series' || type.value === 'tv-shows') && item.first_air_date) {
    return String(item.first_air_date).substring(0, 4)
  }
  return null
}

function goPage (newPage) {
  router.push({
    name: 'Search',
    query: { ...route.query, page: newPage },
  })
}

const resultsList = computed(() => {
  const responseData = searchResult.value
  if (!responseData) return []
  return responseData.data ?? responseData.results ?? []
})

const pagination = computed(() => searchResult.value?.pagination ?? null)
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      {{ translate('search.title') }}
    </h1>
    <form
      class="flex flex-wrap gap-2 mb-6"
      @submit.prevent="submitSearch"
    >
      <Input
        :model-value="localSearchQuery"
        @update:model-value="localSearchQuery = $event"
        type="search"
        :placeholder="translate('search.placeholder')"
      />
      <div class="w-48">
        <Select
          :model-value="localType"
          @update:model-value="localType = $event; submitSearch()"
          :options="typeOptions"
        />
      </div>
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500 whitespace-nowrap">{{ translate('search.per_page') }}</span>
        <div class="w-24">
          <Select
            :model-value="localItemsPerPage"
            @update:model-value="localItemsPerPage = $event; submitSearch()"
            :options="perPageOptions"
          />
        </div>
      </div>
      <Button
        type="submit"
        variant="primary"
      >
        {{ translate('search.button') }}
      </Button>
    </form>

    <div
      v-if="searchError"
      class="mb-4 p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ searchError }}
    </div>
    <div
      v-if="isLoading"
      class="text-gray-500"
    >
      {{ translate('search.loading') }}
    </div>
    <div
      v-if="!isLoading && resultsList.length === 0 && (activeSearchQuery || searchResult)"
      class="text-gray-500"
    >
      {{ translate('search.no_results') }}
<<<<<<< HEAD
=======
      ,{{ isLoading }}, {{ resultsList.length }}, {{ activeSearchQuery }}, {{ searchResult }}
>>>>>>> origin/main
    </div>
    <div
      v-else-if="!isLoading && resultsList.length"
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
    >
    ,{{ isLoading }}, {{ resultsList.length }}, {{ activeSearchQuery }}, {{ searchResult }}
      <Card
        v-for="item in resultsList"
        :key="item.slug || item.suggested_slug || item.id"
        clickable
        @click="router.push(detailPath(item))"
        padding-class="p-4"
      >
        <template #header>
          <div class="flex items-start justify-between mb-2">
            <div>
              <h3 class="text-lg font-bold text-gray-900 leading-tight">{{ itemTitle(item) }}</h3>
              <p v-if="itemSubtitle(item)" class="text-sm text-gray-500 mt-1">{{ itemSubtitle(item) }}</p>
            </div>
            
            <Badge 
              v-if="item.source === 'external'" 
              variant="info" 
              class="shrink-0 ml-2"
            >
              {{ translate('search.badge.tmdb') }}
            </Badge>
            <Badge 
              v-else-if="item.source === 'local'" 
              variant="success" 
              class="shrink-0 ml-2"
            >
              {{ translate('search.badge.local') }}
            </Badge>
          </div>
        </template>
        <div v-if="item.overview" class="text-sm text-gray-600 line-clamp-3">
          {{ item.overview }}
        </div>
      </Card>
    </div>

    <div
      v-if="pagination && (pagination.total_pages > 1 || pagination.page > 1)"
      class="mt-6 flex gap-2 items-center"
    >
      <Button
<<<<<<< HEAD
        :disabled="(pagination.page ?? currentPage) <= 1"
        variant="outline"
        size="sm"
        @click="goPage((pagination.page ?? currentPage) - 1)"
=======
        :disabled="currentPage <= 1"
        variant="outline"
        size="sm"
        @click="goPage(currentPage - 1)"
>>>>>>> origin/main
      >
        {{ translate('search.previous') }}
      </Button>
      <span class="text-gray-600 font-medium">
        {{ translate('search.page_of', { page: pagination.page ?? currentPage, total: pagination.total_pages ?? 1 }) }}
      </span>
      <Button
<<<<<<< HEAD
        :disabled="(pagination.page ?? currentPage) >= (pagination.total_pages ?? 1)"
        variant="outline"
        size="sm"
        @click="goPage((pagination.page ?? currentPage) + 1)"
=======
        :disabled="currentPage >= (pagination.total_pages ?? 1)"
        variant="outline"
        size="sm"
        @click="goPage(currentPage + 1)"
>>>>>>> origin/main
      >
        {{ translate('search.next') }}
      </Button>
    </div>
  </div>
</template>
