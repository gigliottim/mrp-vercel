import { describe, it, expect } from 'vitest'
import { buildXlsx } from './xlsx.js'
import { buildPdf } from './pdf.js'

describe('lib/export', () => {
  it('buildXlsx genera un archivo zip (xlsx) válido', async () => {
    const buf = await buildXlsx([{ name: 'Hoja 1', rows: [['Col A', 'Col B'], [1, 'á é í ó ú ñ']] }])
    expect(buf.subarray(0, 2).toString()).toBe('PK')
    expect(buf.length).toBeGreaterThan(500)
  })

  it('buildPdf genera PDF con título y acentos', async () => {
    const buf = await buildPdf({
      title: 'Listado de Ingeniería',
      subtitle: 'Variante: P-01 · cantidad: 2',
      headers: ['Código', 'Detalle', 'Cantidad'],
      rows: [['P-001', 'Tapa con ñ y acentos áéíóú', 2], [null, 'fila corta', 3.5]],
    })
    expect(buf.subarray(0, 5).toString()).toBe('%PDF-')
  })
})
