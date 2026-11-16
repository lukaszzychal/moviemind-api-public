import { mergeConfig, defineConfig } from 'vitest/config'
import viteConfig from './vite.config.js'

export default defineConfig((env) =>
  mergeConfig(viteConfig(env), {
    test: {
      environment: 'jsdom',
      include: ['src/**/*.spec.js'],
    },
  })
)
