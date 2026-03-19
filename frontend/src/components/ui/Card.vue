<template>
  <div 
    class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow duration-300"
    :class="[clickable ? 'cursor-pointer hover:border-indigo-100' : '']"
  >
    <!-- Optional Header Image/Slot -->
    <div v-if="$slots.image" class="w-full">
      <slot name="image" />
    </div>

    <!-- Padding Container -->
    <div class="p-5" :class="paddingClass">
      <!-- Header -->
      <div v-if="$slots.header || title" class="mb-4">
        <slot name="header">
          <h3 class="text-lg font-bold text-gray-900 leading-tight">{{ title }}</h3>
          <p v-if="subtitle" class="text-sm text-gray-500 mt-1">{{ subtitle }}</p>
        </slot>
      </div>

      <!-- Main Content -->
      <div class="text-gray-700">
        <slot />
      </div>

      <!-- Footer -->
      <div v-if="$slots.footer" class="mt-5 pt-4 border-t border-gray-100 text-sm">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  clickable: {
    type: Boolean,
    default: false
  },
  paddingClass: {
    type: String,
    default: ''
  }
})
</script>
