<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getMovie,
  getMovieRelated,
  getMovieCollection,
  reportMovie,
} from '@/api/client'
import ReportModal from '@/components/ReportModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import { useI18n } from 'vue-i18n'

const { t: translate, locale } = useI18n()

const route = useRoute()
const router = useRouter()
const activeSlug = computed(() => route.params.slug)
const requestedDescriptionId = computed(() => route.query.description_id || null)

const movieData = ref(null)
const acceptedGenerationJob = ref(null)
const relatedMoviesData = ref(null)
const movieCollectionData = ref(null)
const isLoading = ref(true)
const searchError = ref(null)
const isReportModalOpen = ref(false)

async function loadMovie () {
  if (!activeSlug.value) return
  isLoading.value = true
  searchError.value = null
  movieData.value = null
  acceptedGenerationJob.value = null
  try {
    const query = {}
    if (requestedDescriptionId.value) query.description_id = requestedDescriptionId.value
    const apiResponse = await getMovie(activeSlug.value, query)
    if (apiResponse.job_id && apiResponse.status === 'PENDING') {
      acceptedGenerationJob.value = apiResponse
      movieData.value = null
    } else {
      movieData.value = apiResponse
      acceptedGenerationJob.value = null
    }
  } catch (error) {
    if (error.status === 202 && error.data?.job_id) {
      acceptedGenerationJob.value = error.data
    } else {
      searchError.value = error.data?.message || error.message || 'Failed to load movie'
    }
  } finally {
    isLoading.value = false
  }
}

async function loadRelated () {
  if (!activeSlug.value) return
  try {
    relatedMoviesData.value = await getMovieRelated(activeSlug.value)
  } catch {
    relatedMoviesData.value = null
  }
}

async function loadCollection () {
  if (!activeSlug.value) return
  try {
    movieCollectionData.value = await getMovieCollection(activeSlug.value)
  } catch {
    movieCollectionData.value = null
  }
}

watch([activeSlug, locale], () => {
  loadMovie()
  loadRelated()
  loadCollection()
}, { immediate: true })

const selectedDescription = computed(() => {
  const movie = movieData.value
  if (!movie) return null
  if (requestedDescriptionId.value && movie.descriptions) {
    const found = movie.descriptions.find(desc => String(desc.id) === String(requestedDescriptionId.value))
    if (found) return found
  }
  return movie.default_description || (movie.descriptions && movie.descriptions[0]) || null
})

function selectDescription (id) {
  router.replace({ query: { ...route.query, description_id: id } })
}

async function onReport (payload) {
  await reportMovie(activeSlug.value, payload)
}

const relatedMoviesList = computed(() => {
  const responseData = relatedMoviesData.value
  return responseData?.related_movies ?? []
})

const collectionMoviesList = computed(() => movieCollectionData.value?.movies ?? [])

function translatedGenre (genre) {
  const raw = typeof genre === 'object' ? genre.name : genre
  const key = `genres.${raw}`
  const result = translate(key)
  return result === key ? raw : result
}
</script>

<template>
  <div>
    <div
      v-if="isLoading"
      class="text-gray-500"
    >
      {{ translate('detail.loading') }}
    </div>
    <div
      v-else-if="searchError"
      class="p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ searchError }}
    </div>
    <div
      v-else-if="acceptedGenerationJob"
      class="p-4 bg-amber-50 rounded-lg"
    >
      <p class="text-amber-800">
        {{ translate('detail.generating') }}
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: acceptedGenerationJob.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        {{ translate('detail.check_job') }}
      </router-link>
    </div>
    <template v-else-if="movieData">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ movieData.title }}
        </h1>
        <p
          v-if="movieData.release_year"
          class="text-gray-600 mt-1"
        >
          {{ movieData.release_year }}
          <span v-if="movieData.director"> · {{ movieData.director }}</span>
        </p>
        <div
          v-if="movieData.genres && movieData.genres.length"
          class="mt-2 flex flex-wrap gap-2"
        >
          <Badge
            v-for="genre in movieData.genres"
            :key="genre"
            variant="default"
          >
            {{ translatedGenre(genre) }}
          </Badge>
        </div>
      </div>

      <div
        v-if="movieData.descriptions && movieData.descriptions.length > 1"
        class="mb-4"
      >
        <Select
          :label="translate('detail.description_version')"
          :model-value="selectedDescription?.id"
          @update:model-value="selectDescription"
          :options="movieData.descriptions.map(desc => ({
            value: desc.id,
            label: `${desc.locale} ${desc.context_tag ? `(${desc.context_tag})` : ''}`
          }))"
        />
      </div>

      <div
        v-if="selectedDescription"
        class="prose max-w-none mb-8"
      >
        <p class="text-gray-700 whitespace-pre-wrap">
          {{ selectedDescription.text }}
        </p>
      </div>

      <div
        v-if="movieData.people && movieData.people.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.cast_crew') }}
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="person in movieData.people"
            :key="person.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: person.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ person.name }}
              <span
                v-if="person.character_name"
                class="text-gray-500"
              >({{ person.character_name }})</span>
              <span
                v-else-if="person.role"
                class="text-gray-500"
              >({{ person.role }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="relatedMoviesList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.related_movies') }}
        </h2>
        <ul class="space-y-1">
          <li
            v-for="relatedItem in relatedMoviesList"
            :key="relatedItem.slug"
          >
            <router-link
              :to="{ name: 'MovieDetail', params: { slug: relatedItem.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ relatedItem.title }}
              <span
                v-if="relatedItem.release_year"
                class="text-gray-500"
              >({{ relatedItem.release_year }})</span>
              <span
                v-if="relatedItem.relationship_label"
                class="text-gray-400 text-sm"
              > — {{ relatedItem.relationship_label }}</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="collectionMoviesList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.collection') }}
        </h2>
        <p
          v-if="movieCollectionData?.collection?.name"
          class="text-gray-600 mb-2"
        >
          {{ movieCollectionData.collection.name }}
        </p>
        <ul class="space-y-1">
          <li
            v-for="colItem in collectionMoviesList"
            :key="colItem.slug"
          >
            <router-link
              :to="{ name: 'MovieDetail', params: { slug: colItem.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ colItem.title }}
              <span
                v-if="colItem.release_year"
                class="text-gray-500"
              >({{ colItem.release_year }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-4 mt-8">
        <Button
          @click="router.push({ name: 'Generate', query: { entity_type: 'MOVIE', slug: movieData.slug } })"
          variant="primary"
        >
          {{ translate('detail.generate_desc') }}
        </Button>
        <Button
          @click="router.push({ name: 'Compare', query: { type: 'movies', slug1: movieData.slug } })"
          variant="outline"
        >
          {{ translate('detail.compare') }}
        </Button>
        <Button
          @click="isReportModalOpen = true"
          variant="outline"
        >
          {{ translate('detail.report') }}
        </Button>
      </div>
    </template>

    <ReportModal
      v-model="isReportModalOpen"
      :description-id="selectedDescription?.id"
      :on-submit="onReport"
    />
  </div>
</template>
