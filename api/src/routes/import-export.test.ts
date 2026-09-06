import { describe, it, expect, beforeAll } from 'vitest'
import { Hono } from 'hono'
import { importExport } from './import-export'
import { login } from '../test-utils'

let adminToken = ''

beforeAll(async () => {
  adminToken = await login('martin@unik.ar')
})

function makeApp() {
  return new Hono().route('/api/v1/import-export', importExport)
}

describe('import-export partes', () => {
  it('descarga plantilla CSV', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/import-export/partes/template', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const text = await res.text()
    expect(text).toContain('parte_codigo')
    expect(text).toContain('CATALOGO_UM')
    expect(text).toContain('CATALOGO_TIPOS_PARTES')
  })

  it('exporta CSV con datos', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/import-export/partes/export', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const text = await res.text()
    expect(text).toContain('parte_codigo')
    expect(text.split('\n').length).toBeGreaterThan(5)
  })

  it('importa CSV creando parte y variante', async () => {
    const app = makeApp()
    const headers = [
      'parte_codigo', 'parte_detalle', 'tipo_codigo', 'grupo_codigo', 'activo',
      'um_compra_codigo', 'um_uso_codigo', 'factor_conversion',
      'largo_alto', 'um_largo_alto_codigo', 'ancho', 'um_ancho_codigo',
      'espesor_profundidad', 'um_espesor_codigo', 'superficie', 'um_superficie_codigo',
      'volumen', 'um_volumen_codigo',
      'variante_codigo', 'variante_detalle', 'variante_estado',
      'lote_minimo', 'punto_pedido', 'peso', 'um_peso_codigo',
      'ubicacion_cuerpo', 'ubicacion_pasillo', 'ubicacion_estante',
    ]
    const values = [
      'E2E-IMP-001', 'PARTE IMPORT E2E', 'MP', 'N/A', '1',
      '', '', '1',
      '', '', '', '',
      '', '', '1', 'm2',
      '', '',
      'V1', 'VARIANTE IMPORT E2E', 'activa',
      '1', '0', '', '',
      'A', 'P1', 'E1',
    ]
    expect(headers.length).toBe(values.length)
    const csv = [headers.join(';'), values.join(';')].join('\r\n')

    const form = new FormData()
    form.append('archivo', new Blob([csv], { type: 'text/csv' }), 'test.csv')
    const res = await app.request('/api/v1/import-export/partes/import', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}` },
      body: form,
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.total_rows).toBe(1)
    expect(body.data.created_parts).toBe(1)
    expect(body.data.created_variants).toBe(1)
    expect(body.data.ok_rows).toBe(1)

    // Limpiar: eliminar la parte (cascade borra variantes)
    const supabase = await import('../lib/supabase').then((m) => m.createAdminClient())
    const { data: parte } = await supabase.from('partes').select('id').eq('codigo', 'E2E-IMP-001').single()
    if (parte) {
      await supabase.from('partes').delete().eq('id', parte.id)
    }
  })

  it('reporta error por fila inválida', async () => {
    const app = makeApp()
    const csv = [
      'parte_codigo;parte_detalle;tipo_codigo;grupo_codigo;variante_codigo;variante_detalle',
      'X1;Sin tipo;;;V1;Sin grupo',
    ].join('\r\n')
    const form = new FormData()
    form.append('archivo', new Blob([csv], { type: 'text/csv' }), 'bad.csv')
    const res = await app.request('/api/v1/import-export/partes/import', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}` },
      body: form,
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.data.fatal_error).not.toBeNull() // faltan columnas
  })
})

describe('import-export maestro', () => {
  it('descarga plantilla y export', async () => {
    const app = makeApp()
    const tpl = await app.request('/api/v1/import-export/maestro/template', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(tpl.status).toBe(200)
    const exp = await app.request('/api/v1/import-export/maestro/export', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(exp.status).toBe(200)
    const text = await exp.text()
    expect(text).toContain('padre_parte_codigo')
  })
})

describe('geometria y borrado', () => {
  it('recalcula geometria sin errores', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/import-export/geometria/recalcular', {
      method: 'POST',
      headers: { Authorization: `Bearer ${adminToken}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ solo_dimensiones_completas: true }),
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(typeof body.data.actualizados).toBe('number')
  })

  it('verifica borrado de variante', async () => {
    const app = makeApp()
    const res = await app.request('/api/v1/import-export/variantes/1/verificar-borrado', {
      headers: { Authorization: `Bearer ${adminToken}` },
    })
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(typeof body.data.puede_borrar).toBe('boolean')
    expect(Array.isArray(body.data.errores)).toBe(true)
  })
})
