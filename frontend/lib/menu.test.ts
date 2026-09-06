import { describe, it, expect } from 'vitest'
import { groupBySection, type MenuItem } from './menu'

const items: MenuItem[] = [
  { id: 1, code: 'a1', label: 'A1', route: '/a1', icon: null, section_key: 'taller', section_label: 'Taller', parent_id: null, sort_order: 1 },
  { id: 2, code: 'a2', label: 'A2', route: '/a2', icon: null, section_key: 'taller', section_label: 'Taller', parent_id: null, sort_order: 2 },
  { id: 3, code: 'b1', label: 'B1', route: '/b1', icon: null, section_key: 'reportes', section_label: 'Reportes', parent_id: null, sort_order: 1 },
]

describe('groupBySection', () => {
  it('agrupa items por seccion manteniendo orden', () => {
    const groups = groupBySection(items)
    expect(groups.length).toBe(2)
    expect(groups[0].key).toBe('taller')
    expect(groups[0].items.length).toBe(2)
    expect(groups[1].key).toBe('reportes')
    expect(groups[1].items.length).toBe(1)
  })

  it('devuelve vacio con items vacios', () => {
    expect(groupBySection([])).toEqual([])
  })
})
