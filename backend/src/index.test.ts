import { describe, it, expect } from 'vitest'
import { VERSION } from './index'

describe('backend', () => {
  it('expone versión', () => {
    expect(VERSION).toBe('0.1.0')
  })
})
