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

const { t } = useI18n()

const route = useRoute()
const router = useRouter()
const loading = ref(false)
const error = ref(null)
const result = ref(null)

const type = computed(() => route.query.type || 'movies')
const q = computed(() => route.query.q || '')
const page = computed(() => Math.max(1, parseInt(route.query.page, 10) || 1))
const perPage = computed(() => parseInt(route.query.per_page, 10) || 20)

const localQ = ref(route.query.q || '')
const localType = ref(route.query.type || 'movies')
const localPerPage = ref(perPage.value)

watch(() => route.query.q, (newVal) => { localQ.value = newVal || '' })
watch(() => route.query.type, (newVal) => { localType.value = newVal || 'movies' })
watch(() => route.query.per_page, (newVal) => { localPerPage.value = parseInt(newVal, 10) || 20 })

const typeOptions = computed(() => [
  { value: 'movies', label: t('types.movies') },
  { value: 'people', label: t('types.people') },
  { value: 'tv-series', label: t('types.tv_series') },
  { value: 'tv-shows', label: t('types.tv_shows') },
])

const perPageOptions = [
  { value: 10, label: '10' },
  { value: 20, label: '20' },
  { value: 50, label: '50' },
  { value: 100, label: '100' },
]

function submitSearch () {
  router.push({ query: { ...route.query, q: localQ.value, type: localType.value, per_page: localPerPage.value, page: 1 } })
}

let currentSearchId = 0

async function runSearch () {
  const searchId = ++currentSearchId

  error.value = null
  result.value = null
  const params = { page: page.value, per_page: perPage.value }
  if (q.value) params.q = q.value

  loading.value = true
  try {
    let res = null
    switch (type.value) {
      case 'movies':
        res = await searchMovies(params)
        break
      case 'people':
        res = await searchPeople(params)
        break
      case 'tv-series':
        res = await searchTvSeries(params)
        break
      case 'tv-shows':
        res = await searchTvShows(params)
        break
      default:
        res = await searchMovies(params)
    }
    
    if (searchId === currentSearchId) {
      result.value = res
    }
  } catch (e) {
    if (searchId === currentSearchId) {
      error.value = e.data?.message || e.message || 'Search failed'
    }
  } finally {
    if (searchId === currentSearchId) {
      loading.value = false
    }
  }
}

watch([type, q, page, perPage], runSearch, { immediate: true })

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
      {{ t('search.title') }}
    </h1>
    <form
      class="flex flex-wrap gap-2 mb-6"
      @submit.prevent="submitSearch"
    >
      <Input
        :model-value="localQ"
        @update:model-value="localQ = $event"
        type="search"
        :placeholder="t('search.placeholder')"
      />
      <div class="w-48">
        <Select
          :model-value="localType"
          @update:model-value="localType = $event; submitSearch()"
          :options="typeOptions"
        />
      </div>
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500 whitespace-nowrap">{{ t('search.per_page') }}</span>
        <div class="w-24">
          <Select
            :model-value="localPerPage"
            @update:model-value="localPerPage = $event; submitSearch()"
            :options="perPageOptions"
          />
        </div>
      </div>
      <Button
        type="submit"
        variant="primary"
      >
        {{ t('search.button') }}
      </Button>
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
      {{ t('search.loading') }}
    </div>
    <div
      v-if="!loading && items.length === 0 && (q || result)"
      class="text-gray-500"
    >
      {{ t('search.no_results') }}
    </div>
    <div
      v-else-if="!loading && items.length"
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
    >
      <Card
        v-for="item in items"
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
              {{ t('search.badge.tmdb') }}
            </Badge>
            <Badge 
              v-else-if="item.source === 'local'" 
              variant="success" 
              class="shrink-0 ml-2"
            >
              {{ t('search.badge.local') }}
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
        :disabled="page <= 1"
        variant="outline"
        size="sm"
        @click="goPage(page - 1)"
      >
        {{ t('search.previous') }}
      </Button>
      <span class="text-gray-600 font-medium">
        {{ t('search.page_of', { page: pagination.page ?? page, total: pagination.total_pages ?? 1 }) }}
      </span>
      <Button
        :disabled="page >= (pagination.total_pages ?? 1)"
        variant="outline"
        size="sm"
        @click="goPage(page + 1)"
      >
        {{ t('search.next') }}
      </Button>
    </div>
  </div>
</template>
