<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getPerson, getPersonRelated, reportPerson } from '@/api/client'
import ReportModal from '@/components/ReportModal.vue'
import Select from '@/components/ui/Select.vue'
import Button from '@/components/ui/Button.vue'
import { useI18n } from 'vue-i18n'
import { formatDate } from '@/composables/useDate.js'

const { t: translate, locale } = useI18n()

const route = useRoute()
const router = useRouter()
const activeSlug = computed(() => route.params.slug)
const requestedBioId = computed(() => route.query.bio_id || null)
const personData = ref(null)
const acceptedGenerationJob = ref(null)
const relatedPeopleData = ref(null)
const isLoading = ref(true)
const searchError = ref(null)
const isReportModalOpen = ref(false)

async function loadPerson () {
  if (!activeSlug.value) return
  isLoading.value = true
  searchError.value = null
  personData.value = null
  acceptedGenerationJob.value = null
  try {
    const apiResponse = await getPerson(activeSlug.value)
    if (apiResponse.job_id && apiResponse.status === 'PENDING') {
      acceptedGenerationJob.value = apiResponse
      personData.value = null
    } else {
      personData.value = apiResponse
      acceptedGenerationJob.value = null
    }
  } catch (error) {
    searchError.value = error.data?.message || error.message || 'Failed to load person'
  } finally {
    isLoading.value = false
  }
}

async function loadRelated () {
  if (!activeSlug.value) return
  try {
    relatedPeopleData.value = await getPersonRelated(activeSlug.value)
  } catch {
    relatedPeopleData.value = null
  }
}

watch([activeSlug, locale], () => {
  loadPerson()
  loadRelated()
}, { immediate: true })

const selectedBio = computed(() => {
  const person = personData.value
  if (!person) return null
  if (requestedBioId.value && person.bios) {
    const found = person.bios.find(bio => String(bio.id) === String(requestedBioId.value))
    if (found) return found
  }
  return person.default_bio || (person.bios && person.bios[0]) || null
})

function selectBio (id) {
  router.replace({ query: { ...route.query, bio_id: id } })
}

async function onReport (payload) {
  const { description_id, ...rest } = payload
  await reportPerson(activeSlug.value, {
    ...rest,
    bio_id: selectedBio.value?.id,
  })
}

const relatedPeopleList = computed(() => {
  const responseData = relatedPeopleData.value
  return responseData?.related_people ?? responseData?.people ?? []
})

const formattedBirthDate = computed(() => {
  if (!personData.value?.birth_date) return null
  return formatDate(personData.value.birth_date, locale.value)
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
        {{ translate('detail.generating_bio') }}
      </p>
      <router-link
        :to="{ name: 'Job', params: { id: acceptedGenerationJob.job_id } }"
        class="text-indigo-600 hover:underline mt-2 inline-block"
      >
        {{ translate('detail.check_job') }}
      </router-link>
    </div>
    <template v-else-if="personData">
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">
          {{ personData.name }}
        </h1>
        <p
          v-if="formattedBirthDate"
          class="text-gray-600 mt-1"
        >
          {{ formattedBirthDate }}
          <span v-if="personData.birthplace"> · {{ personData.birthplace }}</span>
        </p>
      </div>

      <div
        v-if="personData.bios && personData.bios.length > 1"
        class="mb-4"
      >
        <Select
          :label="translate('detail.bio_version')"
          :model-value="selectedBio?.id"
          @update:model-value="selectBio"
          :options="personData.bios.map(bio => ({
            value: bio.id,
            label: `${bio.locale} ${bio.context_tag ? `(${bio.context_tag})` : ''}`
          }))"
        />
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
        v-if="personData.movies && personData.movies.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.movies') }}
        </h2>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="movie in personData.movies"
            :key="movie.slug"
          >
            <router-link
              :to="{ name: 'MovieDetail', params: { slug: movie.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ movie.title }}
              <span
                v-if="movie.release_year"
                class="text-gray-500"
              >({{ movie.release_year }})</span>
            </router-link>
          </li>
        </ul>
      </div>

      <div
        v-if="relatedPeopleList.length"
        class="mb-8"
      >
        <h2 class="text-xl font-semibold text-gray-900 mb-2">
          {{ translate('detail.related_people') }}
        </h2>
        <ul class="space-y-1">
          <li
            v-for="relatedPerson in relatedPeopleList"
            :key="relatedPerson.slug"
          >
            <router-link
              :to="{ name: 'PersonDetail', params: { slug: relatedPerson.slug } }"
              class="text-indigo-600 hover:underline"
            >
              {{ relatedPerson.name }}
            </router-link>
          </li>
        </ul>
      </div>

      <div class="flex flex-wrap gap-4 mt-8">
        <Button
          @click="router.push({ name: 'Generate', query: { entity_type: 'PERSON', slug: personData.slug } })"
          variant="primary"
        >
          {{ translate('detail.generate_bio') }}
        </Button>
        <Button
          @click="router.push({ name: 'Compare', query: { type: 'people', slug1: personData.slug } })"
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
      :description-id="selectedBio?.id"
      :on-submit="onReport"
    />
  </div>
</template>
