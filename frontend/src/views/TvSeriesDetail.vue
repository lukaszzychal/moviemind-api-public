<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getTvSeriesBySlug,
  getTvSeriesRelated,
} from '@/api/client'
import ReportModal from '@/components/ReportModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/composables/useDate.js'

const { t: translate, locale } = useI18n()

const route = useRoute()
const router = useRouter()
const activeSlug = computed(() => route.params.slug)
const requestedDescriptionId = computed(() => route.query.description_id || null)
const tvSeriesData = ref(null)
const acceptedGenerationJob = ref(null)
const relatedTvSeriesData = ref(null)
const isLoading = ref(true)
const searchError = ref(null)
const isReportModalOpen = ref(false)

async function loadTvSeries () {
  if (!activeSlug.value) return
  isLoading.value = true
  searchError.value = null
  tvSeriesData.value = null
  acceptedGenerationJob.value = null
  try {
    const query = requestedDescriptionId.value ? { description_id: requestedDescriptionId.value } : {}
    const apiResponse = await getTvSeriesBySlug(activeSlug.value, query)
    if (apiResponse.job_id && apiResponse.status === 'PENDING') {
      acceptedGenerationJob.value = apiResponse
      tvSeriesData.value = null
    } else {
      tvSeriesData.value = apiResponse
      acceptedGenerationJob.value = null
    }
  } catch (error) {
    searchError.value = error.data?.message || error.message || 'Failed to load TV series'
  } finally {
    isLoading.value = false
  }
}

async function loadRelated () {
  if (!activeSlug.value) return
  try {
    relatedTvSeriesData.value = await getTvSeriesRelated(activeSlug.value)
  } catch {
    relatedTvSeriesData.value = null
  }
}

watch([activeSlug, locale], () => {
  loadTvSeries()
  loadRelated()
}, { immediate: true })

const selectedDescription = computed(() => {
  const series = tvSeriesData.value
  if (!series) return null
  if (requestedDescriptionId.value && series.descriptions) {
    const found = series.descriptions.find(desc => String(desc.id) === String(requestedDescriptionId.value))
    if (found) return found
  }
  return series.descriptions?.[0] || null
})

function selectDescription (id) {
  router.replace({ query: { ...route.query, description_id: id } })
}

async function onReport (payload) {
  await reportTvSeries(activeSlug.value, payload)
}

const relatedTvSeriesList = computed(() => relatedTvSeriesData.value?.related_tv_series ?? [])

function translatedGenre (genre) {
  const raw = typeof genre === 'object' ? genre.name : genre
  const key = `genres.${raw}`
  const result = translate(key)
  return result === key ? raw : result
}

const activeAirYears = computed(() => {
  const series = tvSeriesData.value
  if (!series) return null
  const startYear = formatDate(series.first_air_date, locale.value, 'year')
  if (!startYear) return null
  const endYear = formatDate(series.last_air_date, locale.value, 'year')
  return endYear && endYear !== startYear ? `${startYear} – ${endYear}` : startYear
})
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
    <template v-else-if="tvSeriesData">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ tvSeriesData.title }}
        </h1>
        <p
          v-if="activeAirYears"
          class="text-gray-600 mt-1"
        >
          {{ activeAirYears }}
        </p>
        <p
          v-if="tvSeriesData.number_of_seasons != null"
          class="text-gray-600"
        >
          {{ translate('detail.seasons_episodes', { seasons: tvSeriesData.number_of_seasons, episodes: tvSeriesData.number_of_episodes }) }}
        </p>
        <div
          v-if="tvSeriesData.genres?.length"
          class="mt-2 flex flex-wrap gap-2"
        >
          <Badge
            v-for="genre in tvSeriesData.genres"
            :key="typeof genre === 'object' ? genre.name : genre"
            variant="default"
          >
            {{ translatedGenre(genre) }}
          </Badge>
        </div>
      </div>

      <div
        v-if="tvSeriesData.descriptions?.length > 1"
        class="mb-4"
      >
        <Select
          :label="translate('detail.description_version')"
          :model-value="selectedDescription?.id"
          @update:model-value="selectDescription"
          :options="tvSeriesData.descriptions.map(desc => ({
            value: desc.id,
            label: `${desc.locale} ${desc.context_tag ? `(${desc.context_tag})` : ''}`
          }))"
        />
      </div>

      <div
        v-if="selectedDescription?.text"
        class="prose max-w-none mb-8"
      >
        <p class="text-gray-700 whitespace-pre-wrap">
          {{ selectedDescription.text }}
        </p>
      </div>

      <div
        v-if="tvSeriesData.people?.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.cast_crew') }}
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="person in tvSeriesData.people"
            :key="person.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: person.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ person.name }}
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="relatedTvSeriesList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.related_series') }}
        </h2>
        <ul class="space-y-1">
          <li
            v-for="relatedItem in relatedTvSeriesList"
            :key="relatedItem.slug"
          >
            <router-link
              :to="{ name: 'TvSeriesDetail', params: { slug: relatedItem.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ relatedItem.title }}
              <span
                v-if="relatedItem.first_air_date"
                class="text-gray-500"
              >({{ relatedItem.first_air_date.substring(0, 4) }})</span>
              <span
                v-if="relatedItem.relationship_label"
                class="text-gray-400 text-sm"
              > — {{ relatedItem.relationship_label }}</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-4 mt-8">
        <Button
          @click="router.push({ name: 'Generate', query: { entity_type: 'TV_SERIES', slug: tvSeriesData.slug } })"
          variant="primary"
        >
          {{ translate('detail.generate_desc') }}
        </Button>
        <Button
          @click="router.push({ name: 'Compare', query: { type: 'tv-series', slug1: tvSeriesData.slug } })"
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
