import pluginVue from 'eslint-plugin-vue'

export default [
  ...pluginVue.configs['flat/recommended'],
  {
    ignores: ['dist', 'node_modules', '**/*.min.js'],
  },
  {
    files: ['src/views/**/*.vue', 'src/components/Layout.vue'],
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
]
