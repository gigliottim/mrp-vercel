import { Hono } from 'hono'
import { requireAuth, requireRole, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

const requireAdmin = requireRole('Super Administrador', 'Administrador')

// ─── Import/Export CSV (Partes+Variantes y Maestro BOM) ─────────────────────
// Port de: PartesVariantesImportService, RowMapper, CsvParser, TemplateService,
// MaestroImportExportService, GeometryRecalculationService, VarianteDeletionService

export const importExport = new Hono<AuthEnv>()
importExport.use('*', requireAuth)

// ─── Helpers CSV ─────────────────────────────────────────────────────────────

function csvEscape(value: string): string {
  if (value.includes(';') || value.includes(',') || value.includes('"') || value.includes('\n')) {
    return '"' + value.replace(/"/g, '""') + '"'
  }
  return value
}

function toCsv(rows: Array<Array<string | number>>, delimiter = ';'): string {
  return rows.map((r) => r.map((v) => csvEscape(String(v))).join(delimiter)).join('\r\n') + '\r\n'
}

/** Parsea CSV con detección de delimitador, BOM y comentarios. */
function parseCsv(text: string, requiredHeaders: string[]): Array<{ line: number; data: Record<string, string> }> {
  const lines = text.replace(/^\uFEFF/, '').split(/\r?\n/)
  let header: string[] = []
  let delimiter = ';'
  const rows: Array<{ line: number; data: Record<string, string> }> = []

  for (let i = 0; i < lines.length; i++) {
    const raw = lines[i]
    if (raw.trim() === '') continue
    if (header.length === 0) {
      delimiter = raw.split(';').length >= raw.split(',').length ? ';' : ','
    }
    const parsed = raw.split(delimiter).map((v) => v.trim().replace(/^"|"$/g, ''))
    if (parsed.length === 0 || parsed[0] === '') continue
    if (parsed[0].startsWith('#')) continue

    if (header.length === 0) {
      header = parsed.map((h) => h.toLowerCase())
      const missing = requiredHeaders.filter((h) => !header.includes(h))
      if (missing.length > 0) {
        throw new Error('Faltan columnas: ' + missing.join(', '))
      }
      continue
    }

    const row: Record<string, string> = {}
    header.forEach((col, idx) => {
      row[col] = parsed[idx] ?? ''
    })
    if (Object.values(row).every((v) => v === '')) continue
    rows.push({ line: i + 1, data: row })
  }

  if (header.length === 0) throw new Error('El archivo no contiene cabecera válida.')
  return rows
}

/** Parsea decimales con formato español (1.234,56 o 1234.56). Acepta number o string. */
function parseDecimal(value: unknown): number | null {
  if (typeof value === 'number') return Number.isFinite(value) ? value : null
  const raw = (value ?? '').trim()
  if (raw === '') return null
  let v = raw
  if (/^-?\d{1,3}(\.\d{3})*(,\d+)?$/.test(v)) {
    v = v.replace(/\./g, '').replace(',', '.')
  } else if (v.includes(',') && !v.includes('.')) {
    v = v.replace(',', '.')
  }
  const n = Number(v)
  return Number.isFinite(n) ? n : null
}

function normalizeKey(v: string): string {
  return v.trim().toUpperCase()
}

async function readMultipartBody(c: { req: { formData(): Promise<FormData> } }): Promise<{ text: string; filename: string }> {
  const form = await c.req.formData()
  const file = form.get('archivo') as File | null
  if (!file) throw new Error('No se recibió el archivo (campo "archivo").')
  return { text: await file.text(), filename: file.name }
}

// ─── Partes + Variantes: plantilla ───────────────────────────────────────────

const PARTES_HEADERS = [
  'parte_codigo', 'parte_detalle', 'tipo_codigo', 'grupo_codigo', 'activo',
  'um_compra_codigo', 'um_uso_codigo', 'factor_conversion',
  'largo_alto', 'um_largo_alto_codigo', 'ancho', 'um_ancho_codigo',
  'espesor_profundidad', 'um_espesor_codigo', 'superficie', 'um_superficie_codigo',
  'volumen', 'um_volumen_codigo',
  'variante_codigo', 'variante_detalle', 'variante_estado',
  'lote_minimo', 'punto_pedido', 'peso', 'um_peso_codigo',
  'ubicacion_cuerpo', 'ubicacion_pasillo', 'ubicacion_estante',
]

async function loadCatalogos(supabase: ReturnType<typeof createUserClient>) {
  const [ums, tipos, grupos] = await Promise.all([
    supabase.from('unidades_medida').select('id, simbolo, unidad, tipo').order('id'),
    supabase.from('tipos_partes').select('id, codigo, nombre').order('id'),
    supabase.from('grupos_partes').select('id, codigo, nombre').order('id'),
  ])
  return {
    ums: (ums.data ?? []) as Array<Record<string, unknown>>,
    tipos: (tipos.data ?? []) as Array<Record<string, unknown>>,
    grupos: (grupos.data ?? []) as Array<Record<string, unknown>>,
  }
}

importExport.get('/partes/template', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { ums, tipos, grupos } = await loadCatalogos(supabase)

  const lines: Array<Array<string | number>> = [PARTES_HEADERS]
  lines.push([
    'PRT-0001', 'Lamina galvanizada 0.8 mm', 'MATERIA_PRIMA', 'CHAPA', '1',
    'mL', 'm2', '1.20', '', '', '1.20', 'm', '0.80', 'mm', '', '', '', '',
    'BASE', 'Bobina estandar', 'activa', '1', '0', '', '', 'A', 'P01', 'E1',
  ])
  lines.push(['# Completa cada parte/variante en una fila.'])
  lines.push(['# Codigos UM: usar simbolo (ej: m, mm, mL, m2, kg, u).'])
  lines.push([])

  lines.push(['# CATALOGO_UM'])
  lines.push(['um_codigo', 'id_um', 'simbolo', 'unidad', 'tipo'])
  for (const u of ums) {
    lines.push([String(u.simbolo ?? ''), String(u.id ?? ''), String(u.simbolo ?? ''), String(u.unidad ?? ''), String(u.tipo ?? '')])
  }
  lines.push([])
  lines.push(['# CATALOGO_TIPOS_PARTES'])
  lines.push(['tipo_codigo', 'tipo_nombre'])
  for (const t of tipos) {
    lines.push([String(t.codigo ?? ''), String(t.nombre ?? '')])
  }
  lines.push([])
  lines.push(['# CATALOGO_GRUPOS_PARTES'])
  lines.push(['grupo_codigo', 'grupo_nombre'])
  for (const g of grupos) {
    lines.push([String(g.codigo ?? ''), String(g.nombre ?? '')])
  }

  return new Response('\uFEFF' + toCsv(lines), {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': 'attachment; filename="plantilla_import_partes_variantes.csv"',
    },
  })
})

// ─── Partes + Variantes: export ──────────────────────────────────────────────

importExport.get('/partes/export', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('partes')
    .select(`id, codigo, detalle, activo, factor_conversion, largo_alto, ancho, espesor_profundidad, superficie, volumen, tipos_partes!partes_id_tipo_fkey(codigo), grupos_partes!partes_id_grupo_fkey(codigo), um_compra:unidades_medida!partes_id_um_compra_fkey(simbolo), um_uso:unidades_medida!partes_id_um_uso_fkey(simbolo), um_largo:unidades_medida!partes_id_um_largo_alto_fkey(simbolo), um_ancho:unidades_medida!partes_id_um_ancho_fkey(simbolo), um_espesor:unidades_medida!partes_id_um_espesor_fkey(simbolo), um_superficie:unidades_medida!partes_id_um_superficie_fkey(simbolo), um_volumen:unidades_medida!partes_id_um_volumen_fkey(simbolo), variantes!variantes_id_parte_fkey(id, codigo_variante, detalle, estado, lote_minimo, punto_pedido, peso, id_um_peso, ubicacion_cuerpo, ubicacion_pasillo, ubicacion_estante, um_peso:unidades_medida!variantes_id_um_peso_fkey(simbolo))`)
    .order('codigo')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  const rows: Array<Array<string | number>> = [PARTES_HEADERS]
  for (const p of (data ?? []) as any[]) {
    const variantes = p.variantes ?? []
    if (variantes.length === 0) {
      rows.push([
        p.codigo, p.detalle, p.tipos_partes?.codigo ?? '', p.grupos_partes?.codigo ?? '', p.activo ? '1' : '0',
        p.um_compra?.simbolo ?? '', p.um_uso?.simbolo ?? '', p.factor_conversion ?? 1,
        p.largo_alto ?? '', p.um_largo?.simbolo ?? '', p.ancho ?? '', p.um_ancho?.simbolo ?? '',
        p.espesor_profundidad ?? '', p.um_espesor?.simbolo ?? '', p.superficie ?? '', p.um_superficie?.simbolo ?? '',
        p.volumen ?? '', p.um_volumen?.simbolo ?? '',
        '', '', 'activa', '1', '0', '', '',
        '', '', '',
      ])
    }
    for (const v of variantes) {
      rows.push([
        p.codigo, p.detalle, p.tipos_partes?.codigo ?? '', p.grupos_partes?.codigo ?? '', p.activo ? '1' : '0',
        p.um_compra?.simbolo ?? '', p.um_uso?.simbolo ?? '', p.factor_conversion ?? 1,
        p.largo_alto ?? '', p.um_largo?.simbolo ?? '', p.ancho ?? '', p.um_ancho?.simbolo ?? '',
        p.espesor_profundidad ?? '', p.um_espesor?.simbolo ?? '', p.superficie ?? '', p.um_superficie?.simbolo ?? '',
        p.volumen ?? '', p.um_volumen?.simbolo ?? '',
        v.codigo_variante, v.detalle, v.estado, v.lote_minimo ?? 1, v.punto_pedido ?? 0,
        v.peso ?? '', v.um_peso?.simbolo ?? '',
        v.ubicacion_cuerpo ?? '', v.ubicacion_pasillo ?? '', v.ubicacion_estante ?? '',
      ])
    }
  }

  return new Response('\uFEFF' + toCsv(rows), {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': 'attachment; filename="export_partes_variantes.csv"',
    },
  })
})

// ─── Partes + Variantes: import ──────────────────────────────────────────────

importExport.post('/partes/import', requireAdmin, async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const report = {
    total_rows: 0, ok_rows: 0, error_rows: 0,
    created_parts: 0, updated_parts: 0, created_variants: 0, updated_variants: 0,
    rows: [] as Array<{ line: number; status: string; message: string }>,
    fatal_error: null as string | null,
  }

  let text: string
  try {
    ;({ text } = await readMultipartBody(c))
  } catch (e) {
    report.fatal_error = (e as Error).message
    return c.json({ data: report })
  }

  let parsed: Array<{ line: number; data: Record<string, string> }>
  try {
    parsed = parseCsv(text, PARTES_HEADERS)
  } catch (e) {
    report.fatal_error = (e as Error).message
    return c.json({ data: report })
  }

  // Lookups
  const { ums, tipos, grupos } = await loadCatalogos(supabase)
  const tipoMap = new Map<string, number>()
  for (const t of tipos) tipoMap.set(normalizeKey(String(t.codigo ?? '')), Number(t.id))
  const grupoMap = new Map<string, number>()
  for (const g of grupos) grupoMap.set(normalizeKey(String(g.codigo ?? '')), Number(g.id))
  const unitByCode = new Map<string, number>()
  const unitById = new Set<number>()
  for (const u of ums) {
    unitByCode.set(normalizeKey(String(u.simbolo ?? '')), Number(u.id))
    unitByCode.set(normalizeKey(String(u.unidad ?? '')), Number(u.id))
    unitById.add(Number(u.id))
  }

  const resolveUnit = (raw: string, field: string, errors: string[]): number | null => {
    const v = raw.trim()
    if (v === '') return null
    if (/^\d+$/.test(v) && unitById.has(Number(v))) return Number(v)
    const key = normalizeKey(v)
    const id = unitByCode.get(key)
    if (id === undefined) {
      errors.push(`${field} no existe (${raw}).`)
      return null
    }
    return id
  }

  for (const entry of parsed) {
    const row = entry.data
    report.total_rows++
    const errors: string[] = []
    const rowReport = { line: entry.line, status: 'error', message: '' }

    const codigoParte = normalizeKey(row['parte_codigo'] ?? '')
    const detalleParte = (row['parte_detalle'] ?? '').trim()
    const tipoCodigo = normalizeKey(row['tipo_codigo'] ?? '')
    const grupoCodigo = normalizeKey(row['grupo_codigo'] ?? '')
    const codigoVariante = normalizeKey(row['variante_codigo'] ?? '')
    const detalleVariante = (row['variante_detalle'] ?? '').trim()
    const estadoVariante = (row['variante_estado'] ?? 'activa').trim().toLowerCase()

    if (!codigoParte) errors.push('parte_codigo es obligatorio.')
    if (!detalleParte) errors.push('parte_detalle es obligatorio.')
    if (!tipoCodigo || !tipoMap.has(tipoCodigo)) errors.push(`tipo_codigo no existe (${row['tipo_codigo']}).`)
    if (!grupoCodigo || !grupoMap.has(grupoCodigo)) errors.push(`grupo_codigo no existe (${row['grupo_codigo']}).`)
    if (!codigoVariante) errors.push('variante_codigo es obligatorio.')
    if (!detalleVariante) errors.push('variante_detalle es obligatorio.')
    if (!['activa', 'desarrollo', 'obsoleta', 'descontinuada'].includes(estadoVariante)) {
      errors.push('variante_estado invalido.')
    }

    const idUmCompra = resolveUnit(row['um_compra_codigo'] ?? '', 'um_compra_codigo', errors)
    const idUmUso = resolveUnit(row['um_uso_codigo'] ?? '', 'um_uso_codigo', errors)
    const factorConversion = parseDecimal(row['factor_conversion'] ?? null)
    if (idUmCompra !== null && idUmUso !== null && idUmCompra !== idUmUso && (factorConversion === null || factorConversion <= 0)) {
      errors.push('factor_conversion es obligatorio cuando um_compra y um_uso son distintas.')
    }

    if (errors.length > 0) {
      report.error_rows++
      rowReport.message = errors.join(' | ')
      report.rows.push(rowReport)
      continue
    }

    try {
      const partData = {
        codigo: codigoParte,
        id_tipo: tipoMap.get(tipoCodigo)!,
        id_grupo: grupoMap.get(grupoCodigo)!,
        detalle: detalleParte,
        activo: ['1', 'true', 'si', 'yes', 'y'].includes((row['activo'] ?? '1').trim().toLowerCase()) ? true : false,
        id_um_compra: idUmCompra,
        id_um_uso: idUmUso,
        factor_conversion: idUmCompra !== null && idUmUso !== null && idUmCompra === idUmUso ? 1 : factorConversion,
        largo_alto: parseDecimal(row['largo_alto'] ?? null),
        id_um_largo_alto: resolveUnit(row['um_largo_alto_codigo'] ?? '', 'um_largo_alto_codigo', []),
        ancho: parseDecimal(row['ancho'] ?? null),
        id_um_ancho: resolveUnit(row['um_ancho_codigo'] ?? '', 'um_ancho_codigo', []),
        espesor_profundidad: parseDecimal(row['espesor_profundidad'] ?? null),
        id_um_espesor: resolveUnit(row['um_espesor_codigo'] ?? '', 'um_espesor_codigo', []),
        superficie: parseDecimal(row['superficie'] ?? null),
        id_um_superficie: resolveUnit(row['um_superficie_codigo'] ?? '', 'um_superficie_codigo', []),
        volumen: parseDecimal(row['volumen'] ?? null),
        id_um_volumen: resolveUnit(row['um_volumen_codigo'] ?? '', 'um_volumen_codigo', []),
      }

      // Upsert parte
      const { data: existente } = await supabase
        .from('partes')
        .select('id')
        .eq('codigo', codigoParte)
        .limit(1)
        .maybeSingle()
      let parteId: number
      let partAction = 'creada'
      if (existente) {
        parteId = Number(existente.id)
        await supabase.from('partes').update(partData).eq('id', parteId)
        report.updated_parts++
        partAction = 'actualizada'
      } else {
        const { data: nueva, error: eNew } = await supabase
          .from('partes')
          .insert({ ...partData, company_id: c.get('companyId') })
          .select('id')
          .single()
        if (eNew) throw new Error(eNew.message)
        parteId = Number(nueva.id)
        report.created_parts++
      }

      // Upsert variante
      const variantData = {
        id_parte: parteId,
        codigo_variante: codigoVariante,
        detalle: detalleVariante,
        estado: estadoVariante,
        lote_minimo: parseDecimal(row['lote_minimo'] ?? null) ?? 1,
        punto_pedido: parseDecimal(row['punto_pedido'] ?? null) ?? 0,
        peso: parseDecimal(row['peso'] ?? null),
        id_um_peso: resolveUnit(row['um_peso_codigo'] ?? '', 'um_peso_codigo', []),
        ubicacion_cuerpo: (row['ubicacion_cuerpo'] ?? '').trim(),
        ubicacion_pasillo: (row['ubicacion_pasillo'] ?? '').trim(),
        ubicacion_estante: (row['ubicacion_estante'] ?? '').trim(),
      }
      const { data: varianteExistente } = await supabase
        .from('variantes')
        .select('id')
        .eq('id_parte', parteId)
        .eq('codigo_variante', codigoVariante)
        .limit(1)
        .maybeSingle()
      let variantAction = 'creada'
      if (varianteExistente) {
        await supabase.from('variantes').update(variantData).eq('id', Number(varianteExistente.id))
        report.updated_variants++
        variantAction = 'actualizada'
      } else {
        const { error: eVar } = await supabase
          .from('variantes')
          .insert({ ...variantData, company_id: c.get('companyId') })
        if (eVar) throw new Error(eVar.message)
        report.created_variants++
      }

      report.ok_rows++
      rowReport.status = 'ok'
      rowReport.message = `Parte ${partAction} y variante ${variantAction} (${codigoParte} / ${codigoVariante}).`
    } catch (e) {
      const msg = (e as Error).message
      if (msg.includes('partes_codigo_key')) rowReport.message = 'Codigo de parte duplicado.'
      else if (msg.includes('variantes_id_parte_codigo_variante_key')) rowReport.message = 'Codigo de variante duplicado para la parte.'
      else rowReport.message = 'Error al guardar fila: ' + msg
      report.error_rows++
    }
    report.rows.push(rowReport)
  }

  return c.json({ data: report })
})

// ─── Maestro BOM: plantilla + export + import ────────────────────────────────

const MAESTRO_HEADERS = ['padre_parte_codigo', 'padre_variante_codigo', 'hijo_parte_codigo', 'hijo_variante_codigo', 'cantidad', 'unidad_codigo']

importExport.get('/maestro/template', async (c) => {
  const rows: Array<Array<string | number>> = [
    MAESTRO_HEADERS,
    ['PROD-001', 'V1', 'MAT-001', 'V1', 2.5, 'kg'],
    ['PROD-001', 'V1', 'MAT-002', 'V1', 1, 'u'],
  ]
  return new Response('\uFEFF' + toCsv(rows, ','), {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': 'attachment; filename="plantilla_import_maestro.csv"',
    },
  })
})

importExport.get('/maestro/export', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data, error } = await supabase
    .from('bom_cabecera')
    .select(`id, variante_padre_id, padre:variantes!bom_cabecera_variante_padre_id_fkey(id, codigo_variante, partes!variantes_id_parte_fkey(codigo)), bom_detalle(id, variante_componente_id, cantidad_necesaria, hijo:variantes!bom_detalle_variante_componente_id_fkey(id, codigo_variante, partes!variantes_id_parte_fkey(codigo)), unidades_medida!bom_detalle_unidad_medida_id_fkey(simbolo))`)
    .eq('activa', true)
    .order('id')
  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  const rows: Array<Array<string | number>> = [MAESTRO_HEADERS]
  for (const b of (data ?? []) as any[]) {
    for (const d of b.bom_detalle ?? []) {
      rows.push([
        b.padre?.partes?.codigo ?? '', b.padre?.codigo_variante ?? '',
        d.hijo?.partes?.codigo ?? '', d.hijo?.codigo_variante ?? '',
        d.cantidad_necesaria, d.unidades_medida?.simbolo ?? '',
      ])
    }
  }
  return new Response('\uFEFF' + toCsv(rows, ','), {
    headers: {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': 'attachment; filename="export_maestro.csv"',
    },
  })
})

importExport.post('/maestro/import', requireAdmin, async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const report = {
    total_rows: 0, ok_rows: 0, error_rows: 0, skipped_rows: 0, created_links: 0,
    rows: [] as Array<{ line: number; status: string; message: string }>,
    fatal_error: null as string | null,
  }

  let text: string
  try {
    ;({ text } = await readMultipartBody(c))
  } catch (e) {
    report.fatal_error = (e as Error).message
    return c.json({ data: report })
  }
  let parsed: Array<{ line: number; data: Record<string, string> }>
  try {
    parsed = parseCsv(text, MAESTRO_HEADERS)
  } catch (e) {
    report.fatal_error = (e as Error).message
    return c.json({ data: report })
  }

  // Lookups: [parte][variante] => id
  const { data: variantes } = await supabase
    .from('variantes')
    .select('id, codigo_variante, partes!variantes_id_parte_fkey(codigo)')
  const varianteMap = new Map<string, number>()
  for (const v of (variantes ?? []) as any[]) {
    varianteMap.set(`${normalizeKey(v.partes?.codigo ?? '')}|${normalizeKey(v.codigo_variante ?? '')}`, Number(v.id))
  }
  const { data: ums } = await supabase.from('unidades_medida').select('id, simbolo').eq('activa', true)
  const unidadMap = new Map<string, number>()
  for (const u of (ums ?? []) as any[]) {
    unidadMap.set(normalizeKey(String(u.simbolo ?? '')), Number(u.id))
  }

  // Grafo: padre_id -> [hijo_id] (de la BD)
  const graph = new Map<number, number[]>()
  const { data: aristas } = await supabase
    .from('bom_cabecera')
    .select('variante_padre_id, bom_detalle(variante_componente_id)')
    .eq('activa', true)
  for (const b of (aristas ?? []) as any[]) {
    for (const d of b.bom_detalle ?? []) {
      const pid = Number(b.variante_padre_id)
      if (!graph.has(pid)) graph.set(pid, [])
      graph.get(pid)!.push(Number(d.variante_componente_id))
    }
  }

  const detectCycle = (padreId: number, hijoId: number): boolean => {
    const visited = new Set<number>()
    const queue = [hijoId]
    while (queue.length > 0) {
      const node = queue.shift()!
      if (node === padreId) return true
      if (visited.has(node)) continue
      visited.add(node)
      for (const child of graph.get(node) ?? []) queue.push(child)
    }
    return false
  }

  const getActiveBom = async (varianteId: number): Promise<number | null> => {
    const { data } = await supabase
      .from('bom_cabecera')
      .select('id')
      .eq('variante_padre_id', varianteId)
      .eq('activa', true)
      .order('created_at', { ascending: false })
      .limit(1)
      .maybeSingle()
    return data ? Number(data.id) : null
  }

  for (const entry of parsed) {
    const row = entry.data
    report.total_rows++
    const rowReport = { line: entry.line, status: 'error', message: '' }

    const ppCod = normalizeKey(row['padre_parte_codigo'] ?? '')
    const pvCod = normalizeKey(row['padre_variante_codigo'] ?? '')
    const hpCod = normalizeKey(row['hijo_parte_codigo'] ?? '')
    const hvCod = normalizeKey(row['hijo_variante_codigo'] ?? '')
    const cantStr = (row['cantidad'] ?? '').trim()
    const umCod = normalizeKey(row['unidad_codigo'] ?? '')

    const padreId = varianteMap.get(`${ppCod}|${pvCod}`)
    const hijoId = varianteMap.get(`${hpCod}|${hvCod}`)
    const unidadId = unidadMap.get(umCod)
    const cantidad = parseDecimal(cantStr)

    const errors: string[] = []
    if (padreId === undefined) errors.push(`Variante padre no encontrada: ${ppCod}/${pvCod}`)
    if (hijoId === undefined) errors.push(`Variante hijo no encontrada: ${hpCod}/${hvCod}`)
    if (unidadId === undefined) errors.push(`Unidad de medida no encontrada: ${umCod}`)
    if (cantidad === null || cantidad <= 0) errors.push(`Cantidad inválida: ${cantStr}`)

    if (errors.length > 0) {
      report.error_rows++
      rowReport.message = errors.join(' | ')
      report.rows.push(rowReport)
      continue
    }

    if (padreId === hijoId) {
      report.error_rows++
      rowReport.message = 'Un producto no puede ser componente de sí mismo.'
      report.rows.push(rowReport)
      continue
    }

    if (detectCycle(padreId!, hijoId!)) {
      report.error_rows++
      rowReport.message = 'Bucle detectado: agregar esta relación crearía un ciclo infinito.'
      report.rows.push(rowReport)
      continue
    }

    // Duplicado?
    const bomId = await getActiveBom(padreId!)
    if (bomId !== null) {
      const { data: dup } = await supabase
        .from('bom_detalle')
        .select('id')
        .eq('bom_id', bomId)
        .eq('variante_componente_id', hijoId)
        .limit(1)
        .maybeSingle()
      if (dup) {
        report.skipped_rows++
        rowReport.status = 'skip'
        rowReport.message = 'Relación ya existe en la BOM (omitida).'
        if (!graph.has(padreId!)) graph.set(padreId!, [])
        graph.get(padreId!)!.push(hijoId!)
        report.rows.push(rowReport)
        continue
      }
    }

    try {
      let targetBomId = bomId
      if (targetBomId === null) {
        const { data: nueva, error: eNueva } = await supabase
          .from('bom_cabecera')
          .insert({
            variante_padre_id: padreId,
            activa: true,
            version: '1.0',
            fecha_efectiva: new Date().toISOString().slice(0, 10),
            company_id: c.get('companyId'),
          })
          .select('id')
          .single()
        if (eNueva) throw new Error(eNueva.message)
        targetBomId = Number(nueva.id)
      }
      const { error: eIns } = await supabase.from('bom_detalle').insert({
        bom_id: targetBomId,
        variante_componente_id: hijoId,
        cantidad_necesaria: cantidad,
        unidad_medida_id: unidadId,
        secuencia: 0,
        company_id: c.get('companyId'),
      })
      if (eIns) throw new Error(eIns.message)

      if (!graph.has(padreId!)) graph.set(padreId!, [])
      graph.get(padreId!)!.push(hijoId!)

      report.created_links++
      report.ok_rows++
      rowReport.status = 'ok'
      rowReport.message = `${ppCod}/${pvCod} → ${hpCod}/${hvCod} (cant: ${cantidad})`
    } catch (e) {
      rowReport.message = 'Error al guardar: ' + (e as Error).message
      report.error_rows++
    }
    report.rows.push(rowReport)
  }

  return c.json({ data: report })
})

// ─── Geometría: recalcular ───────────────────────────────────────────────────

importExport.post('/geometria/recalcular', requireAdmin, async (c) => {
  const body = await c.req.json().catch(() => null)
  const soloCompletas = body?.solo_dimensiones_completas === true
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  // Unidades de longitud con equivalencia base (metros)
  const { data: ums } = await supabase
    .from('unidades_medida')
    .select('id, tipo, simbolo, equivalencia_base')
    .in('tipo', ['longitud', 'superficie', 'volumen'])
  const longitudes = new Map<number, number>()
  let defaultSuperficieId: number | null = null
  let defaultVolumenId: number | null = null
  for (const u of (ums ?? []) as any[]) {
    if (u.tipo === 'longitud') longitudes.set(Number(u.id), Number(u.equivalencia_base ?? 0))
    if (u.tipo === 'superficie' && (String(u.simbolo).includes('m²') || String(u.simbolo).includes('mts²'))) defaultSuperficieId = Number(u.id)
    if (u.tipo === 'volumen' && String(u.simbolo).toLowerCase().includes('cm')) defaultVolumenId = Number(u.id)
  }

  const { data: partes } = await supabase.from('partes').select('*').order('id')
  let actualizados = 0
  let errores = 0

  for (const p of (partes ?? []) as any[]) {
    const l = parseDecimal(p.largo_alto)
    const a = parseDecimal(p.ancho)
    const e = parseDecimal(p.espesor_profundidad)
    if (l === null || a === null) {
      if (soloCompletas) continue
    }
    const update: Record<string, unknown> = {}
    try {
      if (l !== null && a !== null) {
        const lMetros = l * (longitudes.get(Number(p.id_um_largo_alto)) ?? 1)
        const aMetros = a * (longitudes.get(Number(p.id_um_ancho)) ?? 1)
        const superficie = lMetros * aMetros
        update.superficie = superficie
        update.id_um_superficie = defaultSuperficieId
        if (e !== null) {
          const eMetros = e * (longitudes.get(Number(p.id_um_espesor)) ?? 1)
          update.volumen = superficie * eMetros
          update.id_um_volumen = defaultVolumenId
        }
      }
      if (Object.keys(update).length > 0) {
        const { error } = await supabase.from('partes').update(update).eq('id', Number(p.id))
        if (error) throw new Error(error.message)
        actualizados++
      }
    } catch {
      errores++
    }
  }

  return c.json({ data: { actualizados, errores } })
})

// ─── Variante: verificar borrado seguro ──────────────────────────────────────

importExport.get('/variantes/:id/verificar-borrado', async (c) => {
  const varianteId = Number(c.req.param('id'))
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const errores: string[] = []

  const { data: compras } = await supabase
    .from('compras_detalle')
    .select('id')
    .eq('variante_id', varianteId)
    .limit(1)
  if ((compras ?? []).length > 0) errores.push('Tiene registros de compras asociados.')

  const { data: movs } = await supabase
    .from('movimientos_inventario')
    .select('id')
    .eq('variante_id', varianteId)
    .limit(1)
  if ((movs ?? []).length > 0) errores.push('Tiene movimientos de stock registrados.')

  const { data: hijas } = await supabase
    .from('bom_detalle')
    .select('bom_cabecera!bom_detalle_bom_id_fkey(variante_padre_id)')
    .eq('variante_componente_id', varianteId)
    .limit(5)
  if ((hijas ?? []).length > 0) {
    const padres = (hijas ?? []).map((h: any) => String(h.bom_cabecera?.variante_padre_id ?? '?'))
    errores.push(`Es componente de las variantes padre: ${padres.join(', ')}.`)
  }

  const { data: padreBom } = await supabase
    .from('bom_cabecera')
    .select('id')
    .eq('variante_padre_id', varianteId)
    .eq('activa', true)
    .limit(1)
  if ((padreBom ?? []).length > 0) errores.push('Tiene una estructura de composición (BOM) definida como padre.')

  return c.json({ data: { puede_borrar: errores.length === 0, errores } })
})
