// Servidor local de la API para verificación E2E manual (no se commitea)
import { serve } from '@hono/node-server'
import app from './src/index.js'

serve({ fetch: app.fetch, port: 8787 }, () => {
  console.log('API local en http://localhost:8787')
})
