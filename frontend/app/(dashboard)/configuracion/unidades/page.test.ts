import { describe, it, expect } from 'vitest'
import { tipoLabel } from './unidades-labels'

describe('tipoLabel', () => {
  it('mapea tipos conocidos', () => {
    expect(tipoLabel('longitud')).toBe('Longitud')
    expect(tipoLabel('superficie')).toBe('Superficie')
    expect(tipoLabel('masa')).toBe('Masa')
    expect(tipoLabel('unidad')).toBe('Unidad')
  })

  it('devuelve el valor si no conoce el tipo', () => {
    expect(tipoLabel('otro')).toBe('otro')
  })
})
