import { describe, it, expect } from 'vitest'

describe('supabase server client', () => {
  it('requiere variables de entorno', () => {
    expect(process.env.NEXT_PUBLIC_SUPABASE_URL).toBeTruthy()
    expect(process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY).toBeTruthy()
  })
})
