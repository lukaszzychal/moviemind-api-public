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

const { t, locale } = useI18n()

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)
const descriptionId = computed(() => route.query.description_id || null)

const movie = ref(null)
const acceptedGeneration = ref(null)
const related = ref(null)
const collection = ref(null)
const loading = ref(true)
const error = ref(null)
const reportOpen = ref(false)

async function loadMovie () {
  if (!slug.value) return
  loading.value = true
  error.value = null
  movie.value = null
  acceptedGeneration.value = null
  try {
    const query = {}
    if (descriptionId.value) query.description_id = descriptionId.value
    const data = await getMovie(slug.value, query)
    if (data.job_id && data.status === 'PENDING') {
      acceptedGeneration.value = data
      movie.value = null
    } else {
      movie.value = data
      acceptedGeneration.value = null
    }
  } catch (e) {
    if (e.status === 202 && e.data?.job_id) {
      acceptedGeneration.value = e.data
    } else {
      error.value = e.data?.message || e.message || 'Failed to load movie'
    }
  } finally {
    loading.value = false
  }
}

async function loadRelated () {
  if (!slug.value) return
  try {
    related.value = await getMovieRelated(slug.value)
  } catch {
    related.value = null
  }
}

async function loadCollection () {
  if (!slug.value) return
  try {
    collection.value = await getMovieCollection(slug.value)
  } catch {
    collection.value = null
  }
}

watch([slug, locale], () => {
  loadMovie()
  loadRelated()
  loadCollection()
}, { immediate: true })

const selectedDescription = computed(() => {
  const m = movie.value
  if (!m) return null
  if (descriptionId.value && m.descriptions) {
    const found = m.descriptions.find(d => String(d.id) === String(descriptionId.value))
    if (found) return found
  }
  return m.default_description || (m.descriptions && m.descriptions[0]) || null
})

function selectDescription (id) {
  router.replace({ query: { ...route.query, description_id: id } })
}

async function onReport (payload) {
  await reportMovie(slug.value, payload)
}

const relatedList = computed(() => {
  const r = related.value
  return r?.related_movies ?? []
})

const collectionMovies = computed(() => collection.value?.movies ?? [])
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
    <template v-else-if="movie">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ movie.title }}
        </h1>
        <p
          v-if="movie.release_year"
          class="text-gray-600 mt-1"
        >
          {{ movie.release_year }}
          <span v-if="movie.director"> · {{ movie.director }}</span>
        </p>
        <div
          v-if="movie.genres && movie.genres.length"
          class="mt-2 flex flex-wrap gap-2"
        >
          <Badge
            v-for="g in movie.genres"
            :key="g"
            variant="default"
          >
            {{ g }}
          </Badge>
        </div>
      </div>

      <div
        v-if="movie.descriptions && movie.descriptions.length > 1"
        class="mb-4"
      >
        <Select
          :label="t('detail.description_version')"
          :model-value="selectedDescription?.id"
          @update:model-value="selectDescription"
          :options="movie.descriptions.map(d => ({
            value: d.id,
            label: `${d.locale} ${d.context_tag ? `(${d.context_tag})` : ''}`
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
        v-if="movie.people && movie.people.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.cast_crew') }}
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="p in movie.people"
            :key="p.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: p.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ p.name }}
              <span
                v-if="p.character_name"
                class="text-gray-500"
              >({{ p.character_name }})</span>
              <span
                v-else-if="p.role"
                class="text-gray-500"
              >({{ p.role }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="relatedList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.related_movies') }}
        </h2>
        <ul class="space-y-1">
          <li
            v-for="r in relatedList"
            :key="r.slug"
          >
            <router-link
              :to="{ name: 'MovieDetail', params: { slug: r.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ r.title }}
              <span
                v-if="r.release_year"
                class="text-gray-500"
              >({{ r.release_year }})</span>
              <span
                v-if="r.relationship_label"
                class="text-gray-400 text-sm"
              > — {{ r.relationship_label }}</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="collectionMovies.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ t('detail.collection') }}
        </h2>
        <p
          v-if="collection?.collection?.name"
          class="text-gray-600 mb-2"
        >
          {{ collection.collection.name }}
        </p>
        <ul class="space-y-1">
          <li
            v-for="m in collectionMovies"
            :key="m.slug"
          >
            <router-link
              :to="{ name: 'MovieDetail', params: { slug: m.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ m.title }}
              <span
                v-if="m.release_year"
                class="text-gray-500"
              >({{ m.release_year }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-4 mt-8">
        <Button
          @click="router.push({ name: 'Generate', query: { entity_type: 'MOVIE', slug: movie.slug } })"
          variant="primary"
        >
          {{ t('detail.generate_desc') }}
        </Button>
        <Button
          @click="router.push({ name: 'Compare', query: { type: 'movies', slug1: movie.slug } })"
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
