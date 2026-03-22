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

const { t, locale } = useI18n()

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)
const descriptionId = computed(() => route.query.description_id || null)
const tvSeries = ref(null)
const acceptedGeneration = ref(null)
const related = ref(null)
const loading = ref(true)
const error = ref(null)
const reportOpen = ref(false)

async function load () {
  if (!slug.value) return
  loading.value = true
  error.value = null
  tvSeries.value = null
  acceptedGeneration.value = null
  try {
    const query = descriptionId.value ? { description_id: descriptionId.value } : {}
    const data = await getTvSeriesBySlug(slug.value, query)
    if (data.job_id && data.status === 'PENDING') {
      acceptedGeneration.value = data
      tvSeries.value = null
    } else {
      tvSeries.value = data
      acceptedGeneration.value = null
    }
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to load TV series'
  } finally {
    loading.value = false
  }
}

async function loadRelated () {
  if (!slug.value) return
  try {
    related.value = await getTvSeriesRelated(slug.value)
  } catch {
    related.value = null
  }
}

watch([slug, locale], () => {
  load()
  loadRelated()
}, { immediate: true })

const selectedDescription = computed(() => {
  const s = tvSeries.value
  if (!s) return null
  if (descriptionId.value && s.descriptions) {
    const found = s.descriptions.find(d => String(d.id) === String(descriptionId.value))
    if (found) return found
  }
  return s.descriptions?.[0] || null
})

function selectDescription (id) {
  router.replace({ query: { ...route.query, description_id: id } })
}

async function onReport (payload) {
  await reportTvSeries(slug.value, payload)
}

const relatedList = computed(() => related.value?.related_tv_series ?? [])

function translatedGenre (genre) {
  const raw = typeof genre === 'object' ? genre.name : genre
  const key = `genres.${raw}`
  const result = t(key)
  return result === key ? raw : result
}

const airYears = computed(() => {
  const s = tvSeries.value
  if (!s) return null
  const start = formatDate(s.first_air_date, locale.value, 'year')
  if (!start) return null
  const end = formatDate(s.last_air_date, locale.value, 'year')
  return end && end !== start ? `${start} – ${end}` : start
})
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="text-gray-500"
    >
      {{ t('detail.loading') }}
    </div>
    <div
      v-else-if="error"
      class="p-4 bg-red-50 text-red-700 rounded-lg"
    >
      {{ error }}
    </div>
    <div
      v-else-if="acceptedGeneration"
      class="p-4 bg-amber-50 rounded-lg"
    >
      <p class="text-amber-800">
        {{ t('detail.generating') }}
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: acceptedGeneration.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        {{ t('detail.check_job') }}
      </router-link>
    </div>
    <template v-else-if="tvSeries">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ tvSeries.title }}
        </h1>
        <p
          v-if="airYears"
          class="text-gray-600 mt-1"
        >
          {{ airYears }}
        </p>
        <p
          v-if="tvSeries.number_of_seasons != null"
          class="text-gray-600"
        >
          {{ t('detail.seasons_episodes', { seasons: tvSeries.number_of_seasons, episodes: tvSeries.number_of_episodes }) }}
        </p>
        <div
          v-if="tvSeries.genres?.length"
          class="mt-2 flex flex-wrap gap-2"
        >
          <Badge
            v-for="g in tvSeries.genres"
            :key="typeof g === 'object' ? g.name : g"
            variant="default"
          >
            {{ translatedGenre(g) }}
          </Badge>
        </div>
      </div>

      <div
        v-if="tvSeries.descriptions?.length > 1"
        class="mb-4"
      >
        <Select
          :label="t('detail.description_version')"
          :model-value="selectedDescription?.id"
          @update:model-value="selectDescription"
          :options="tvSeries.descriptions.map(d => ({
            value: d.id,
            label: `${d.locale} ${d.context_tag ? `(${d.context_tag})` : ''}`
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
        v-if="tvSeries.people?.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.cast_crew') }}
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="p in tvSeries.people"
            :key="p.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: p.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ p.name }}
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="relatedList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.related_series') }}
        </h2>
        <ul class="space-y-1">
          <li
            v-for="r in relatedList"
            :key="r.slug"
          >
            <router-link
              :to="{ name: 'TvSeriesDetail', params: { slug: r.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ r.title }}
              <span
                v-if="r.first_air_date"
                class="text-gray-500"
              >({{ r.first_air_date }})</span>
              <span
                v-if="r.relationship_label"
                class="text-gray-400 text-sm"
              > — {{ r.relationship_label }}</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-4 mt-8">
        <Button
          @click="router.push({ name: 'Generate', query: { entity_type: 'TV_SERIES', slug: tvSeries.slug } })"
          variant="primary"
        >
          {{ t('detail.generate_desc') }}
        </Button>
        <Button
          @click="router.push({ name: 'Compare', query: { type: 'tv-series', slug1: tvSeries.slug } })"
          variant="outline"
        >
          {{ t('detail.compare') }}
        </Button>
        <Button
          @click="reportOpen = true"
          variant="outline"
        >
          {{ t('detail.report') }}
        </Button>
      </div>
    </template>

    <ReportModal
      v-model="reportOpen"
      :description-id="selectedDescription?.id"
      :on-submit="onReport"
    />
  </div>
</template>
