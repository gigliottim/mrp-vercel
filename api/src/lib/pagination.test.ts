import { describe, it, expect } from 'vitest'
import { parsePagination } from './pagination.js'

describe('parsePagination', () => {
  it('usa defaults', () => {
    expect(parsePagination({})).toEqual({ page: 1, perPage: 20, offset: 0 })
  })
  it('respeta valores', () => {
    expect(parsePagination({ page: '3', perPage: '10' })).toEqual({ page: 3, perPage: 10, offset: 20 })
  })
  it('clampa perPage a 500', () => {
    expect(parsePagination({ perPage: '500' }).perPage).toBe(500)
    expect(parsePagination({ perPage: '1000' }).perPage).toBe(500)
  })
  it('clampa page a minimo 1', () => {
    expect(parsePagination({ page: '0' }).page).toBe(1)
  })
})
