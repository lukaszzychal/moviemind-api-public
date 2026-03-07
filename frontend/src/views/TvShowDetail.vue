<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  getTvShow,
  getTvShowRelated,
  reportTvShow,
} from '@/api/client'
import ReportModal from '@/components/ReportModal.vue'

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)
const descriptionId = computed(() => route.query.description_id || null)
const tvShow = ref(null)
const acceptedGeneration = ref(null)
const related = ref(null)
const loading = ref(true)
const error = ref(null)
const reportOpen = ref(false)

async function load () {
  if (!slug.value) return
  loading.value = true
  error.value = null
  tvShow.value = null
  acceptedGeneration.value = null
  try {
    const query = descriptionId.value ? { description_id: descriptionId.value } : {}
    const data = await getTvShow(slug.value, query)
    if (data.job_id && data.status === 'PENDING') {
      acceptedGeneration.value = data
      tvShow.value = null
    } else {
      tvShow.value = data
      acceptedGeneration.value = null
    }
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to load TV show'
  } finally {
    loading.value = false
  }
}

async function loadRelated () {
  if (!slug.value) return
  try {
    related.value = await getTvShowRelated(slug.value)
  } catch {
    related.value = null
  }
}

watch(slug, () => {
  load()
  loadRelated()
}, { immediate: true })

const selectedDescription = computed(() => {
  const s = tvShow.value
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
  await reportTvShow(slug.value, payload)
}

const relatedList = computed(() => related.value?.related_tv_shows ?? related.value?.data ?? [])
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="text-gray-500"
    >
      Loading...
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
        Description is being generated.
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: acceptedGeneration.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        Check job status
      </router-link>
    </div>
    <template v-else-if="tvShow">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ tvShow.title }}
        </h1>
        <p
          v-if="tvShow.first_air_date"
          class="text-gray-600 mt-1"
        >
          {{ tvShow.first_air_date }}
        </p>
      </div>

      <div
        v-if="tvShow.descriptions?.length > 1"
        class="mb-4"
      >
        <label class="block text-sm font-medium text-gray-700 mb-1">Description version</label>
        <select
          :value="selectedDescription?.id"
          class="rounded border border-gray-300 px-3 py-2"
          @change="(e) => selectDescription(e.target.value)"
        >
          <option
            v-for="d in tvShow.descriptions"
            :key="d.id"
            :value="d.id"
          >
            {{ d.locale }} {{ d.context_tag ? `(${d.context_tag})` : '' }}
          </option>
        </select>
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
        v-if="relatedList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          Related shows
        </h2>
        <ul class="space-y-1">
          <li
            v-for="r in relatedList"
            :key="r.slug"
          >
            <router-link
              :to="{ name: 'TvShowDetail', params: { slug: r.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ r.title }}
              <span
                v-if="r.first_air_date"
                class="text-gray-500"
              >({{ r.first_air_date }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex gap-4">
        <router-link
          :to="{ name: 'Generate', query: { entity_type: 'TV_SHOW', slug: tvShow.slug } }"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
        >
          Generate description
        </router-link>
        <router-link
          :to="{ name: 'Compare', query: { type: 'tv-shows', slug1: tvShow.slug } }"
          class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
        >
          Compare with another
        </router-link>
        <button
          type="button"
          class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50"
          @click="reportOpen = true"
        >
          Report issue
        </button>
      </div>
    </template>

    <ReportModal
      v-model="reportOpen"
      :description-id="selectedDescription?.id"
      :on-submit="(payload) => reportTvShow(slug.value, payload)"
    />
  </div>
</template>
