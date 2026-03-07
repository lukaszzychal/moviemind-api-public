<script setup>
import { ref } from 'vue'
import { postFeedback } from '@/api/client'

const message = ref('')
const category = ref('other')
const sending = ref(false)
const error = ref(null)
const success = ref(false)

const categories = [
  { value: 'bug', label: 'Bug' },
  { value: 'suggestion', label: 'Suggestion' },
  { value: 'other', label: 'Other' },
]

async function submit () {
  const msg = message.value?.trim()
  if (!msg || msg.length < 10) {
    error.value = 'Message must be at least 10 characters'
    return
  }
  error.value = null
  success.value = false
  sending.value = true
  try {
    await postFeedback({ message: msg, category: category.value })
    success.value = true
    message.value = ''
  } catch (e) {
    error.value = e.data?.message || e.message || 'Failed to send feedback'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-4">
      Feedback
    </h1>
    <p class="text-gray-600 mb-6">
      Send anonymous feedback (no personal data). Optional category.
    </p>

    <form
      v-if="!success"
      class="space-y-4"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
        <select
          v-model="category"
          class="w-full rounded border border-gray-300 px-3 py-2"
        >
          <option
            v-for="opt in categories"
            :key="opt.value"
            :value="opt.value"
          >
            {{ opt.label }}
          </option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Message (min 10 characters)</label>
        <textarea
          v-model="message"
          rows="5"
          class="w-full rounded border border-gray-300 px-3 py-2"
          required
        />
      </div>
      <p
        v-if="error"
        class="text-red-600 text-sm"
      >
        {{ error }}
      </p>
      <button
        type="submit"
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
        :disabled="sending"
      >
        {{ sending ? 'Sending...' : 'Send feedback' }}
      </button>
    </form>

    <div
      v-else
      class="p-4 bg-green-50 text-green-800 rounded-lg"
    >
      Feedback received. Thank you.
    </div>
  </div>
</template>
