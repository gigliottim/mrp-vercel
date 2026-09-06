import { defineConfig } from 'vitest/config'
import { loadEnv } from 'vite'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, '../', '')
  return {
    test: {
      environment: 'node',
      include: ['src/**/*.test.ts'],
      // Los tests de integración golpean Supabase Auth: correr en un solo
      // proceso (forks) y secuencial evita el rate limit 429 por logins
      // paralelos. El cache singleton de test-utils.ts comparte tokens.
      pool: 'forks',
      poolOptions: {
        forks: {
          // Todos los test files en el MISMO proceso fork: comparten globalThis
          // (tokens de login) y evitan el rate limit 429 de Supabase Auth.
          singleFork: true,
        },
      },
      fileParallelism: false,
      env: {
        SUPABASE_URL: env.SUPABASE_URL,
        SUPABASE_ANON_KEY: env.SUPABASE_ANON_KEY,
        SUPABASE_SERVICE_ROLE_KEY: env.SUPABASE_SERVICE_ROLE_KEY,
      },
      // Tests de integración contra Supabase real: 30s por test
      testTimeout: 30000,
      hookTimeout: 30000,
    },
  }
})
