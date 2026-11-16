<template>
  <div class="text-center max-w-2xl mx-auto py-12">
    <p class="text-sm font-semibold text-indigo-600">
      404
    </p>
    <h1 class="mt-2 text-3xl font-bold text-gray-900">
      {{ $t('not_found.title') }}
    </h1>
    <p class="mt-3 text-gray-600">
      {{ $t('not_found.message') }}
    </p>
    <router-link
      to="/"
      class="mt-8 inline-block rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
    >
      {{ $t('not_found.back_home') }}
    </router-link>
  </div>
</template>
