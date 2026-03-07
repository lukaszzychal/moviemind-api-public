<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPerson, getPersonRelated, reportPerson } from '@/api/client'
import ReportModal from '@/components/ReportModal.vue'

const route = useRoute()
const router = useRouter()
const slug = computed(() => route.params.slug)
const bioId = computed(() => route.query.bio_id || null)
const person = ref(null)
const acceptedGeneration = ref(null)
const related = ref(null)
const loading = ref(true)
const error = ref(null)
const reportOpen = ref(false)

async function loadPerson () {
  if (!slug.value) return
  loading.value = true
  error.value = null
  person.value = null
  acceptedGeneration.value = null
  try {
    const data = await getPerson(slug.value)
    if (data.job_id && data.status === 'PENDING') {
      acceptedGeneration.value = data
      person.value = null
    } else {
      person.value = data
      acceptedGeneration.value = null
    }
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to load person'
  } finally {
    loading.value = false
  }
}

async function loadRelated () {
  if (!slug.value) return
  try {
    related.value = await getPersonRelated(slug.value)
  } catch {
    related.value = null
  }
}

watch(slug, () => {
  loadPerson()
  loadRelated()
}, { immediate: true })

const selectedBio = computed(() => {
  const p = person.value
  if (!p) return null
  if (bioId.value && p.bios) {
    const found = p.bios.find(b => String(b.id) === String(bioId.value))
    if (found) return found
  }
  return p.default_bio || (p.bios && p.bios[0]) || null
})

function selectBio (id) {
  router.replace({ query: { ...route.query, bio_id: id } })
}

async function onReport (payload) {
  const { description_id, ...rest } = payload
  await reportPerson(slug.value, {
    ...rest,
    bio_id: selectedBio.value?.id,
  })
}

const relatedList = computed(() => {
  const r = related.value
  return r?.related_people ?? r?.people ?? []
})
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
        Bio is being generated.
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: acceptedGeneration.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        Check job status
      </router-link>
    </div>
    <template v-else-if="person">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ person.name }}
        </h1>
        <p
          v-if="person.birth_date"
          class="text-gray-600 mt-1"
        >
          {{ person.birth_date }}
          <span v-if="person.birthplace"> · {{ person.birthplace }}</span>
        </p>
      </div>

      <div
        v-if="person.bios && person.bios.length > 1"
        class="mb-4"
      >
        <label class="block text-sm font-medium text-gray-700 mb-1">Bio version</label>
        <select
          :value="selectedBio?.id"
          class="rounded border border-gray-300 px-3 py-2"
          @change="(e) => selectBio(e.target.value)"
        >
          <option
            v-for="b in person.bios"
            :key="b.id"
            :value="b.id"
          >
            {{ b.locale }} {{ b.context_tag ? `(${b.context_tag})` : '' }}
          </option>
        </select>
      </div>

      <div
        v-if="selectedBio"
        class="prose max-w-none mb-8"
      >
        <p class="text-gray-700 whitespace-pre-wrap">
          {{ selectedBio.text }}
        </p>
      </div>

      <div
        v-if="person.movies && person.movies.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          Movies
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="m in person.movies"
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

      <div
        v-if="relatedList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          Related people
        </h2>
        <ul class="space-y-1">
          <li
            v-for="r in relatedList"
            :key="r.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: r.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ r.name }}
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex gap-4">
        <router-link
          :to="{ name: 'Generate', query: { entity_type: 'PERSON', slug: person.slug } }"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
        >
          Generate bio
        </router-link>
        <router-link
          :to="{ name: 'Compare', query: { type: 'people', slug1: person.slug } }"
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
      :description-id="selectedBio?.id"
      :on-submit="onReport"
    />
  </div>
</template>
