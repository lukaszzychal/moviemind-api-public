<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  compareMovies,
  comparePeople,
  compareTvSeries,
  compareTvShows,
} from '@/api/client'

const route = useRoute()
const router = useRouter()
const type = computed(() => route.query.type || 'movies')
const slug1 = ref(route.query.slug1 || '')
const slug2 = ref(route.query.slug2 || '')
const result = ref(null)
const loading = ref(false)
const error = ref(null)

const typeOptions = [
  { value: 'movies', label: 'Movies' },
  { value: 'people', label: 'People' },
  { value: 'tv-series', label: 'TV Series' },
  { value: 'tv-shows', label: 'TV Shows' },
]

watch([type], () => {
  result.value = null
  error.value = null
})

async function runCompare () {
  const s1 = slug1.value?.trim()
  const s2 = slug2.value?.trim()
  if (!s1 || !s2) {
    error.value = 'Enter both slugs'
    return
  }
  if (s1 === s2) {
    error.value = 'Slugs must be different'
    return
  }
  error.value = null
  result.value = null
  loading.value = true
  try {
    switch (type.value) {
      case 'movies':
        result.value = await compareMovies(s1, s2)
        break
      case 'people':
        result.value = await comparePeople(s1, s2)
        break
      case 'tv-series':
        result.value = await compareTvSeries(s1, s2)
        break
      case 'tv-shows':
        result.value = await compareTvShows(s1, s2)
        break
      default:
        result.value = await compareMovies(s1, s2)
    }
    router.replace({
      query: { type: type.value, slug1: s1, slug2: s2 },
    })
  } catch (e) {
    error.value = e.data?.message || e.message || 'Compare failed'
  } finally {
    loading.value = false
  }
}

function detailPath (slug, entityType) {
  switch (entityType) {
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
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      Compare
    </h1>
    <form
      class="flex flex-wrap gap-4 mb-6"
      @submit.prevent="runCompare"
    >
      <select
        :value="type"
        class="rounded border border-gray-300 px-3 py-2"
        @change="(e) => router.replace({ query: { ...route.query, type: e.target.value } })"
      >
        <option
          v-for="opt in typeOptions"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
      <input
        v-model="slug1"
        type="text"
        placeholder="First slug"
        class="rounded border border-gray-300 px-3 py-2 flex-1 min-w-[180px]"
      >
      <input
        v-model="slug2"
        type="text"
        placeholder="Second slug"
        class="rounded border border-gray-300 px-3 py-2 flex-1 min-w-[180px]"
      >
      <button
        type="submit"
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
        :disabled="loading"
      >
        {{ loading ? 'Comparing...' : 'Compare' }}
      </button>
    </form>

    <p
      v-if="error"
      class="mb-4 text-red-600"
    >
      {{ error }}
    </p>

    <div
      v-if="result"
      class="grid grid-cols-1 md:grid-cols-2 gap-6"
    >
      <div class="border border-gray-200 rounded-lg p-4">
        <h2 class="font-semibold text-gray-900 mb-2">
          <router-link
            :to="detailPath(result.movie1?.slug || result.person1?.slug || result.tv_series1?.slug || result.tv_show1?.slug, type)"
            class="text-indigo-600 hover:underline"
          >
            {{ result.movie1?.title || result.person1?.name || result.tv_series1?.title || result.tv_show1?.title }}
          </router-link>
        </h2>
        <p
          v-if="result.movie1"
          class="text-gray-600 text-sm"
        >
          {{ result.movie1.release_year }} · {{ result.movie1.director }}
        </p>
        <p
          v-if="result.person1"
          class="text-gray-600 text-sm"
        >
          {{ result.person1.birth_date }} · {{ result.person1.birthplace }}
        </p>
        <p
          v-if="result.tv_series1 || result.tv_show1"
          class="text-gray-600 text-sm"
        >
          {{ (result.tv_series1 || result.tv_show1).first_air_date }}
        </p>
      </div>
      <div class="border border-gray-200 rounded-lg p-4">
        <h2 class="font-semibold text-gray-900 mb-2">
          <router-link
            :to="detailPath(result.movie2?.slug || result.person2?.slug || result.tv_series2?.slug || result.tv_show2?.slug, type)"
            class="text-indigo-600 hover:underline"
          >
            {{ result.movie2?.title || result.person2?.name || result.tv_series2?.title || result.tv_show2?.title }}
          </router-link>
        </h2>
        <p
          v-if="result.movie2"
          class="text-gray-600 text-sm"
        >
          {{ result.movie2.release_year }} · {{ result.movie2.director }}
        </p>
        <p
          v-if="result.person2"
          class="text-gray-600 text-sm"
        >
          {{ result.person2.birth_date }} · {{ result.person2.birthplace }}
        </p>
        <p
          v-if="result.tv_series2 || result.tv_show2"
          class="text-gray-600 text-sm"
        >
          {{ (result.tv_series2 || result.tv_show2).first_air_date }}
        </p>
      </div>
    </div>

    <div
      v-if="result?.comparison"
      class="mt-6 p-4 bg-gray-50 rounded-lg"
    >
      <h3 class="font-semibold text-gray-900 mb-2">
        Comparison
      </h3>
      <dl class="space-y-1 text-sm">
        <template v-if="result.comparison.common_genres?.length">
          <dt class="text-gray-600">
            Common genres
          </dt>
          <dd class="text-gray-900">
            {{ result.comparison.common_genres.join(', ') }}
          </dd>
        </template>
        <template v-if="result.comparison.year_difference != null">
          <dt class="text-gray-600">
            Year difference
          </dt>
          <dd class="text-gray-900">
            {{ result.comparison.year_difference }}
          </dd>
        </template>
        <template v-if="result.comparison.similarity_score != null">
          <dt class="text-gray-600">
            Similarity score
          </dt>
          <dd class="text-gray-900">
            {{ (result.comparison.similarity_score * 100).toFixed(1) }}%
          </dd>
        </template>
        <template v-if="result.comparison.common_people?.length">
          <dt class="text-gray-600">
            Common people
          </dt>
          <dd class="text-gray-900">
            <span
              v-for="(cp, i) in result.comparison.common_people"
              :key="i"
            >
              {{ cp.person?.name ?? cp.name }}
              <span v-if="i < result.comparison.common_people.length - 1">, </span>
            </span>
          </dd>
        </template>
        <template v-if="result.comparison.common_movies_count != null">
          <dt class="text-gray-600">
            Common movies
          </dt>
          <dd class="text-gray-900">
            {{ result.comparison.common_movies_count }}
          </dd>
        </template>
        <template v-if="result.comparison.birth_year_difference != null">
          <dt class="text-gray-600">
            Birth year difference
          </dt>
          <dd class="text-gray-900">
            {{ result.comparison.birth_year_difference }}
          </dd>
        </template>
      </dl>
    </div>
  </div>
</template>
