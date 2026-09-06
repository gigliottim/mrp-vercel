import { Hono } from 'hono'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

// ─── Agente AI: flujos guiados (port de AgentService + PromptBuilder) ───────

// Estado de conversación en memoria (serverless: sin Valkey)
const stateCache = new Map<string, { intent: string; data: Record<string, unknown>; step: number; expiresAt: number }>()
const TTL_MS = 30 * 60 * 1000

function getState(convId: string): { intent: string; data: Record<string, unknown>; step: number } | null {
  const s = stateCache.get(convId)
  if (!s) return null
  if (Date.now() > s.expiresAt) {
    stateCache.delete(convId)
    return null
  }
  return { intent: s.intent, data: s.data, step: s.step }
}

function saveState(convId: string, intent: string, data: Record<string, unknown>, step: number): void {
  stateCache.set(convId, { intent, data, step, expiresAt: Date.now() + TTL_MS })
}

function clearState(convId: string): void {
  stateCache.delete(convId)
}

// ─── Definiciones de campos (port de PromptBuilder) ─────────────────────────

const PARTE_FIELDS = [
  { field: 'id_tipo', message: '¿Qué tipo de parte es?', required: true, lookup: 'tipos_partes' },
  { field: 'codigo', message: '¿Cuál es el código de la parte? (máx 50 caracteres, ej: P-001)', required: true },
  { field: 'id_grupo', message: '¿A qué grupo pertenece?', required: true, lookup: 'grupos_partes' },
  { field: 'detalle', message: '¿Cuál es la descripción de la parte?', required: true },
  { field: 'id_um_compra', message: '¿Cuál es la unidad de medida de compra?', required: true, lookup: 'unidades_medida_all' },
  { field: 'id_um_uso', message: '¿Cuál es la unidad de medida de uso en producción? (Enter para usar la misma que compra)', required: false, lookup: 'unidades_medida_all' },
  { field: 'largo_alto', message: '¿Cuál es el largo/alto? (en mm, Enter para omitir)', required: false },
  { field: 'ancho', message: '¿Cuál es el ancho? (en mm, Enter para omitir)', required: false },
  { field: 'espesor_profundidad', message: '¿Cuál es el espesor/profundidad? (en mm, Enter para omitir)', required: false },
  { field: 'superficie', message: '¿Cuál es la superficie? (en m², Enter para usar el valor calculado)', required: false },
  { field: 'volumen', message: '¿Cuál es el volumen? (en cm³ o ml, Enter para usar el valor calculado)', required: false },
]

const VARIANTE_FIELDS = [
  { field: 'codigo_variante', message: '¿Cuál es el código de la variante?', required: true },
  { field: 'detalle_variante', message: '¿Cuál es la descripción de la variante? (Enter para usar la misma que la parte)', required: false },
  { field: 'estado', message: '¿Cuál es el estado de la variante?', required: true, lookup: 'estados_variante' },
  { field: 'lote_minimo', message: '¿Cuál es el lote mínimo? (default 1)', required: false },
  { field: 'punto_pedido', message: '¿Cuál es el punto de pedido? (default 0)', required: false },
  { field: 'peso', message: '¿Cuál es el peso unitario en kg? (Enter para omitir)', required: false },
  { field: 'ubicacion_cuerpo', message: '¿Ubicación física - Cuerpo? (Enter para omitir)', required: false },
  { field: 'ubicacion_pasillo', message: '¿Ubicación física - Pasillo? (Enter para omitir)', required: false },
  { field: 'ubicacion_estante', message: '¿Ubicación física - Estante? (Enter para omitir)', required: false },
]

const SUPPLIER_FIELDS = [
  { field: 'tipo', message: '¿Qué tipo de entidad es?', required: true, lookup: 'tipos_entidad' },
  { field: 'razon_social', message: '¿Cuál es la razón social?', required: true },
  { field: 'identificacion_tributaria', message: '¿Cuál es el CUIT/CUIL? (Enter para omitir)', required: false },
  { field: 'contacto_email', message: '¿Cuál es el email de contacto? (Enter para omitir)', required: false },
  { field: 'contacto_telefono', message: '¿Cuál es el teléfono de contacto? (Enter para omitir)', required: false },
  { field: 'direccion', message: '¿Cuál es la dirección? (Enter para omitir)', required: false },
]

const ESTADOS_VARIANTE = [
  { value: 'activa', label: 'Activa' },
  { value: 'desarrollo', label: 'En desarrollo' },
  { value: 'obsoleta', label: 'Obsoleta' },
  { value: 'descontinuada', label: 'Descontinuada' },
]

const TIPOS_ENTIDAD = [
  { value: 'PROVEEDOR', label: 'Proveedor' },
  { value: 'CLIENTE', label: 'Cliente' },
  { value: 'AMBOS', label: 'Ambos (Proveedor y Cliente)' },
]

const SUGGESTIONS = [
  { intent: 'create_part', label: 'Crear nueva Parte (Pieza, MP, Conjuntos, PT, MO, etc)', description: 'Te guío para registrar código, tipo, grupo, descripción, UOM y variantes', icon: 'bi-gear', offline_safe: true },
  { intent: 'create_bom', label: 'Armar lista de materiales (BOM)', description: 'Definí la pieza padre y sus componentes de nivel 1', icon: 'bi-diagram-3', offline_safe: true },
  { intent: 'create_supplier', label: 'Registrar Clientes y proveedores', description: 'Tipo, razón social, CUIT/CUIL, email, teléfono y dirección', icon: 'bi-people', offline_safe: true },
]

const LOOKUP_FIELDS = ['id_tipo', 'id_grupo', 'id_um_compra', 'id_um_uso', 'estado', 'tipo']

// ─── Helpers ─────────────────────────────────────────────────────────────────

function detectIntent(input: string): string {
  const i = input.toLowerCase()
  const labels: Record<string, string[]> = {
    create_part: ['crear nueva parte', 'nueva parte', 'crear parte', 'crear nueva pieza', 'nueva pieza', 'crear pieza', 'pieza', 'registrar materia prima', 'materia prima', 'registrar material', 'material', 'nuevo material', 'conjunto', 'producto terminado', 'mano de obra'],
    create_bom: ['armar lista de materiales', 'bom', 'lista de materiales', 'materiales', 'componentes', 'armar bom', 'nueva bom'],
    create_supplier: ['registrar proveedor', 'proveedor', 'nuevo proveedor', 'registrar empresa', 'registrar cliente', 'cliente', 'nuevo cliente', 'registrar entidad', 'entidad', 'clientes y proveedores'],
  }
  for (const [intent, phrases] of Object.entries(labels)) {
    for (const p of phrases) {
      if (i.includes(p)) return intent
    }
  }
  return 'general_query'
}

function isGuidedIntent(intent: string): boolean {
  return ['create_part', 'create_supplier', 'create_bom'].includes(intent)
}

function nextMissingField(intent: string, data: Record<string, unknown>): { field: string; message: string; suggestions: Array<{ label: string; value: string }>; lookup: string | null } | null {
  const phase = (data['_phase'] as string) ?? 'parte'

  if (intent === 'create_part') {
    if (phase === 'parte') {
      for (const f of PARTE_FIELDS) {
        const v = data[f.field]
        if (v === undefined || v === null || v === '') {
          return { field: f.field, message: f.message, suggestions: [], lookup: f.lookup ?? null }
        }
      }
      // primera variante
      const suggested = `${String(data['codigo'] ?? 'P').toUpperCase()}-01`
      return {
        field: 'codigo_variante',
        message: `Ahora vamos a crear la primer variante. ¿Cuál es el código de la variante? (se sugiere ${suggested})`,
        suggestions: [{ label: suggested, value: suggested }],
        lookup: null,
      }
    }
    if (phase === 'variante' || phase === 'otra_variante') {
      const current = (data['_current_variante'] as Record<string, unknown>) ?? {}
      const variantes = (data['variantes'] as unknown[]) ?? []
      const prefix = `Variante ${variantes.length + 1}: `
      for (const f of VARIANTE_FIELDS) {
        const v = current[f.field]
        if (v === undefined || v === null || v === '') {
          const suggestions: Array<{ label: string; value: string }> = []
          if (f.field === 'codigo_variante') {
            const suggested = `${String(data['codigo'] ?? 'P').toUpperCase()}-${String(variantes.length + 1).padStart(2, '0')}`
            suggestions.push({ label: suggested, value: suggested })
          }
          if (f.lookup === 'estados_variante') suggestions.push(...ESTADOS_VARIANTE)
          return { field: f.field, message: prefix + f.message, suggestions, lookup: f.lookup ?? null }
        }
      }
      return {
        field: 'add_more_variante',
        message: 'Variante completada. ¿Querés agregar otra variante o finalizar?',
        suggestions: [{ label: 'Agregar otra variante', value: 'si' }, { label: 'Finalizar', value: 'no' }],
        lookup: null,
      }
    }
    return null
  }

  if (intent === 'create_supplier') {
    for (const f of SUPPLIER_FIELDS) {
      const v = data[f.field]
      if (f.required && (v === undefined || v === null || v === '')) {
        const suggestions = f.lookup === 'tipos_entidad' ? TIPOS_ENTIDAD : []
        return { field: f.field, message: f.message, suggestions, lookup: f.lookup ?? null }
      }
      // Opcionales: solo undefined cuenta como faltante (null = omitido explícitamente)
      if (!f.required && v === undefined) {
        return { field: f.field, message: f.message, suggestions: [], lookup: f.lookup ?? null }
      }
    }
    return null
  }

  if (intent === 'create_bom') {
    if (!data['parent_part']) {
      return { field: 'parent_part', message: '¿Cuál es el código de la pieza padre (producto terminado)?', suggestions: [], lookup: null }
    }
    const comp = (data['_current_component'] as Record<string, unknown>) ?? {}
    if (!comp['code']) {
      return { field: 'component_code', message: '¿Cuál es el código del componente?', suggestions: [], lookup: null }
    }
    if (!comp['quantity']) {
      return { field: 'component_qty', message: '¿Cuál es la cantidad necesaria?', suggestions: [], lookup: null }
    }
    return {
      field: 'add_more',
      message: 'Componente agregado. ¿Querés agregar otro componente?',
      suggestions: [{ label: 'Sí, agregar otro', value: 'si' }, { label: 'No, finalizar', value: 'no' }],
      lookup: null,
    }
  }

  return null
}

function extractCode(input: string): string | null {
  const m = input.match(/[A-Za-z0-9][A-Za-z0-9\-_]{0,49}/)
  return m ? m[0].toUpperCase() : null
}

function extractPositiveNumber(input: string): number | null {
  const m = input.replace(',', '.').match(/\d+(\.\d+)?/)
  if (!m) return null
  const n = Number(m[0])
  return n > 0 ? n : null
}

function extractEmail(input: string): string | null {
  const m = input.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/)
  return m ? m[0] : null
}

function extractCuit(input: string): string | null {
  const m = input.match(/\d{2}-?\d{8}-?\d/)
  return m ? m[0] : null
}

function extractTipoEntidad(input: string): string | null {
  const i = input.toLowerCase()
  if (i.includes('cliente') && i.includes('proveedor')) return 'AMBOS'
  if (i.includes('cliente')) return 'CLIENTE'
  if (i.includes('proveedor')) return 'PROVEEDOR'
  return null
}

function extractFieldValue(field: string, input: string, intent: string): unknown {
  const v = input.trim()
  switch (field) {
    case 'codigo':
    case 'codigo_variante':
    case 'razon_social':
    case 'detalle':
    case 'detalle_variante':
    case 'ubicacion_cuerpo':
    case 'ubicacion_pasillo':
    case 'ubicacion_estante':
    case 'direccion':
    case 'parent_part':
    case 'component_code':
      return v
    case 'id_tipo':
    case 'id_grupo':
    case 'id_um_compra':
    case 'id_um_uso':
      return Number(v) > 0 ? Number(v) : null
    case 'estado':
      return v || 'activa'
    case 'tipo':
      return extractTipoEntidad(v) ?? (['PROVEEDOR', 'CLIENTE', 'AMBOS'].includes(v.toUpperCase()) ? v.toUpperCase() : null)
    case 'lote_minimo':
      return extractPositiveNumber(v) ?? 1
    case 'punto_pedido':
      return extractPositiveNumber(v) ?? 0
    case 'peso':
      return extractPositiveNumber(v)
    case 'largo_alto':
    case 'ancho':
    case 'espesor_profundidad':
    case 'superficie':
    case 'volumen':
      return extractPositiveNumber(v)
    case 'component_qty':
      return extractPositiveNumber(v)
    case 'identificacion_tributaria':
      return extractCuit(v) ?? v
    case 'contacto_email':
      return extractEmail(v) ?? v
    case 'contacto_telefono':
      return v
    default:
      return v
  }
}

function getDefaultForField(field: string, data: Record<string, unknown>): unknown {
  switch (field) {
    case 'id_um_uso': return data['id_um_compra'] ?? null
    case 'detalle_variante': return data['detalle'] ?? null
    case 'estado': return 'activa'
    case 'lote_minimo': return 1
    case 'punto_pedido': return 0
    default: return null
  }
}

// ─── Route ───────────────────────────────────────────────────────────────────

export const agent = new Hono<AuthEnv>()
agent.use('*', requireAuth)

agent.get('/suggestions', async (c) => {
  return c.json({ data: SUGGESTIONS })
})

agent.get('/status', async (c) => {
  const key = process.env.AGENT_AI_API_KEY
  const endpoint = process.env.AGENT_AI_API_ENDPOINT ?? 'https://ollama.com/v1/chat/completions'
  const model = process.env.AGENT_AI_API_MODEL ?? 'deepseek-v4-flash:0731'
  const configValid = Boolean(key && endpoint && model)
  return c.json({ data: { isOnline: configValid, configValid, apiResponds: configValid, mode: 'api' } })
})

agent.get('/lookup/:type', async (c) => {
  const type = c.req.param('type')
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  let data: Array<{ value: string; label: string }> = []

  if (type === 'tipos_partes') {
    const { data: rows } = await supabase.from('tipos_partes').select('id, codigo, nombre').order('id')
    data = (rows ?? []).map((r: any) => ({ value: String(r.id), label: `${r.nombre} (${r.codigo})` }))
  } else if (type === 'grupos_partes') {
    const { data: rows } = await supabase.from('grupos_partes').select('id, codigo, nombre').order('id')
    data = (rows ?? []).map((r: any) => ({ value: String(r.id), label: `${r.nombre} (${r.codigo})` }))
  } else if (type === 'unidades_medida_all') {
    const { data: rows } = await supabase.from('unidades_medida').select('id, simbolo, unidad').eq('activa', true).order('id')
    data = (rows ?? []).map((r: any) => ({ value: String(r.id), label: `${r.simbolo} - ${r.unidad}` }))
  } else if (type === 'estados_variante') {
    data = ESTADOS_VARIANTE.map((e) => ({ value: e.value, label: e.label }))
  } else if (type === 'tipos_entidad') {
    data = TIPOS_ENTIDAD.map((e) => ({ value: e.value, label: e.label }))
  } else {
    return c.json({ error: { code: 'VALIDATION', message: 'Tipo de lookup no válido' } }, 400)
  }

  return c.json({ data })
})

// ─── Guardado (port de savePart/saveSupplier/saveBom) ───────────────────────

async function savePart(supabase: ReturnType<typeof createUserClient>, companyId: number, data: Record<string, unknown>): Promise<{ success: boolean; message: string; data?: unknown }> {
  const codigo = String(data['codigo'] ?? data['code'] ?? '').toUpperCase()
  if (!codigo) return { success: false, message: 'Código de parte requerido' }

  // duplicado
  const { data: existente } = await supabase.from('partes').select('id').eq('codigo', codigo).limit(1).maybeSingle()
  if (existente) return { success: false, message: `El código "${codigo}" ya existe en otra parte.` }

  const idTipo = Number(data['id_tipo'] ?? 1)
  const idGrupo = Number(data['id_grupo'] ?? 1)
  const idUmCompra = data['id_um_compra'] ? Number(data['id_um_compra']) : null
  const idUmUso = data['id_um_uso'] ? Number(data['id_um_uso']) : idUmCompra
  const factorConversion = idUmCompra && idUmUso && idUmCompra === idUmUso ? 1 : (data['factor_conversion'] ? Number(data['factor_conversion']) : null)

  const largoAlto = data['largo_alto'] ? Number(data['largo_alto']) : null
  const ancho = data['ancho'] ? Number(data['ancho']) : null
  const espesor = data['espesor_profundidad'] ? Number(data['espesor_profundidad']) : null
  let superficie = data['superficie'] ? Number(data['superficie']) : null
  let volumen = data['volumen'] ? Number(data['volumen']) : null

  // auto-cálculo (mm → m², mm → cm³)
  if (superficie === null && largoAlto !== null && ancho !== null) {
    superficie = Math.round((largoAlto / 1000) * (ancho / 1000) * 1e6) / 1e6
  }
  if (volumen === null && largoAlto !== null && ancho !== null && espesor !== null) {
    volumen = Math.round((largoAlto / 10) * (ancho / 10) * (espesor / 10) * 1e3) / 1e3
  }

  // unidades por defecto
  const { data: ums } = await supabase.from('unidades_medida').select('id, tipo, simbolo').eq('activa', true)
  const umRows = (ums ?? []) as any[]
  const umByTipo = (tipo: string, simbolo: string) => umRows.find((u) => u.tipo === tipo && u.simbolo === simbolo)?.id ?? null
  const idUmLargo = umByTipo('longitud', 'mm')
  const idUmSuperficie = umByTipo('superficie', 'm²')
  const idUmVolumen = umByTipo('volumen', 'cm³')
  const idUmPeso = umByTipo('masa', 'kg')

  const { data: nueva, error } = await supabase
    .from('partes')
    .insert({
      codigo,
      id_tipo: idTipo,
      id_grupo: idGrupo,
      detalle: String(data['detalle'] ?? data['description'] ?? ''),
      id_um_compra: idUmCompra,
      id_um_uso: idUmUso,
      factor_conversion: factorConversion,
      largo_alto: largoAlto,
      id_um_largo_alto: largoAlto !== null ? idUmLargo : null,
      ancho,
      id_um_ancho: ancho !== null ? idUmLargo : null,
      espesor_profundidad: espesor,
      id_um_espesor: espesor !== null ? idUmLargo : null,
      superficie,
      id_um_superficie: superficie !== null ? idUmSuperficie : null,
      volumen,
      id_um_volumen: volumen !== null ? idUmVolumen : null,
      activo: true,
      company_id: companyId,
    })
    .select('id')
    .single()
  if (error) return { success: false, message: 'Error al guardar la parte: ' + error.message }

  const parteId = Number(nueva.id)

  // variantes
  let variantes = (data['variantes'] as Array<Record<string, unknown>>) ?? []
  if (variantes.length === 0) {
    variantes = [{ codigo_variante: `${codigo}-01`, detalle_variante: String(data['detalle'] ?? '') }]
  }
  const varianteIds: number[] = []
  for (let i = 0; i < variantes.length; i++) {
    const v = variantes[i]
    const codigoVar = String(v['codigo_variante'] ?? `${codigo}-${String(i + 1).padStart(2, '0')}`).toUpperCase()
    const { data: varNueva, error: eVar } = await supabase
      .from('variantes')
      .insert({
        id_parte: parteId,
        codigo_variante: codigoVar,
        detalle: String(v['detalle_variante'] ?? v['detalle'] ?? data['detalle'] ?? ''),
        estado: String(v['estado'] ?? 'activa'),
        lote_minimo: Number(v['lote_minimo'] ?? 1),
        punto_pedido: Number(v['punto_pedido'] ?? 0),
        stock_seguridad: Number(v['stock_seguridad'] ?? 0),
        stock_actual: 0,
        peso: v['peso'] ? Number(v['peso']) : null,
        id_um_peso: v['peso'] ? idUmPeso : null,
        ubicacion_cuerpo: v['ubicacion_cuerpo'] ?? null,
        ubicacion_pasillo: v['ubicacion_pasillo'] ?? null,
        ubicacion_estante: v['ubicacion_estante'] ?? null,
        company_id: companyId,
      })
      .select('id')
      .single()
    if (eVar) return { success: false, message: 'Error al guardar la variante: ' + eVar.message }
    varianteIds.push(Number(varNueva.id))
  }

  return {
    success: true,
    message: `Parte '${codigo}' creada correctamente con ${varianteIds.length} variante(s) (ID: ${parteId})`,
    data: { parte_id: parteId, variante_ids: varianteIds, code: codigo },
  }
}

async function saveSupplier(supabase: ReturnType<typeof createUserClient>, companyId: number, data: Record<string, unknown>): Promise<{ success: boolean; message: string; data?: unknown }> {
  const tipo = String(data['tipo'] ?? 'PROVEEDOR').toUpperCase()
  const razonSocial = String(data['razon_social'] ?? '')
  if (!razonSocial) return { success: false, message: 'Razón social requerida' }

  const cuit = data['identificacion_tributaria'] ? String(data['identificacion_tributaria']) : null
  if (cuit) {
    const { data: dup } = await supabase.from('entidades').select('id').eq('identificacion_tributaria', cuit).limit(1).maybeSingle()
    if (dup) return { success: false, message: `El CUIT/CUIL "${cuit}" ya está registrado en otra entidad.` }
  }

  const { data: nueva, error } = await supabase
    .from('entidades')
    .insert({
      razon_social: razonSocial,
      tipo,
      identificacion_tributaria: cuit,
      contacto_email: data['contacto_email'] ?? null,
      contacto_telefono: data['contacto_telefono'] ?? null,
      direccion: data['direccion'] ?? null,
      company_id: companyId,
    })
    .select('id')
    .single()
  if (error) return { success: false, message: 'Error al guardar la entidad: ' + error.message }

  const tipoLabel = tipo === 'CLIENTE' ? 'Cliente' : tipo === 'AMBOS' ? 'Proveedor y Cliente' : 'Proveedor'
  return {
    success: true,
    message: `${tipoLabel} '${razonSocial}' creado correctamente (ID: ${nueva.id})`,
    data: { id: nueva.id, razon_social: razonSocial, tipo },
  }
}

async function saveBom(supabase: ReturnType<typeof createUserClient>, companyId: number, data: Record<string, unknown>): Promise<{ success: boolean; message: string; data?: unknown }> {
  const parentCode = String(data['parent_part'] ?? '')
  if (!parentCode) return { success: false, message: 'Pieza padre requerida' }

  const { data: padre } = await supabase
    .from('variantes')
    .select('id')
    .eq('codigo_variante', parentCode)
    .limit(1)
    .maybeSingle()
  if (!padre) {
    return { success: false, message: `No se encontró la pieza padre '${parentCode}'. Creéla primero.` }
  }

  const { data: bom, error: eBom } = await supabase
    .from('bom_cabecera')
    .insert({
      variante_padre_id: padre.id,
      version: '1.0',
      activa: true,
      fecha_efectiva: new Date().toISOString().slice(0, 10),
      company_id: companyId,
    })
    .select('id')
    .single()
  if (eBom) return { success: false, message: 'Error al crear la BOM: ' + eBom.message }

  const components = (data['components'] as Array<Record<string, unknown>>) ?? []
  let count = 0
  for (const comp of components) {
    const code = String(comp['code'] ?? comp['component_code'] ?? '')
    if (!code) continue
    const { data: variante } = await supabase
      .from('variantes')
      .select('id')
      .eq('codigo_variante', code)
      .limit(1)
      .maybeSingle()
    if (!variante) continue
    const { error: eDet } = await supabase.from('bom_detalle').insert({
      bom_id: bom.id,
      variante_componente_id: variante.id,
      cantidad_necesaria: Number(comp['quantity'] ?? comp['cantidad'] ?? 1),
      unidad_medida_id: 1,
      company_id: companyId,
    })
    if (!eDet) count++
  }

  return {
    success: true,
    message: `BOM creado para '${parentCode}' (BOM ID: ${bom.id}, componentes: ${count})`,
    data: { bom_id: bom.id, parent_part: parentCode, components_count: count },
  }
}

// ─── Procesamiento de mensajes ──────────────────────────────────────────────

async function askNextField(convId: string, intent: string, data: Record<string, unknown>, step: number): Promise<{ status: string; message: string; data: unknown; suggestions: Array<{ label: string; value: string }> }> {
  const next = nextMissingField(intent, data)
  if (next === null) {
    return {
      status: 'preview',
      message: 'Datos completos. ¿Confirmamos y guardamos?',
      data,
      suggestions: [{ label: 'Confirmar y guardar', value: 'confirmar' }, { label: 'Corregir', value: 'corregir' }],
    }
  }
  return { status: 'clarify', message: next.message, data, suggestions: next.suggestions }
}

agent.post('/message', async (c) => {
  const body = await c.req.json().catch(() => null)
  let convId = String(body?.conversation_id ?? '')
  const userInput = String(body?.message ?? '').trim()
  const intentHint = String(body?.intent ?? '')
  const fieldValue = body?.field_value ? String(body.field_value) : null
  const fieldLabel = body?.field_label ? String(body.field_label) : null
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const companyId = c.get('companyId')

  if (!userInput && !convId) {
    return c.json({ error: { code: 'VALIDATION', message: 'Mensaje vacío' } }, 400)
  }

  // Nueva conversación: generar ID
  if (!convId) {
    convId = `conv_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
  }

  let state = convId ? getState(convId) : null
  let intent = state?.intent ?? intentHint ?? ''
  let data = state?.data ?? {}
  let step = state?.step ?? 0

  if (!intent) {
    intent = detectIntent(userInput)
  }

  // Primer mensaje con intent guiado: iniciar flujo
  if (step === 0 && isGuidedIntent(intent)) {
    data = { _phase: 'parte' }
    step = 1
    saveState(convId, intent, data, step)
    const next = nextMissingField(intent, data)
    return c.json({
      data: {
        conversation_id: convId,
        status: 'clarify',
        message: next?.message ?? '¿Qué querés hacer?',
        data,
        suggestions: next?.suggestions ?? [],
        lookup: next?.lookup ?? null,
        guided_state: { intent, data, step },
      },
    })
  }

  // Continuación de flujo guiado
  if (isGuidedIntent(intent)) {
    const phase = (data['_phase'] as string) ?? 'parte'

    // Fase: ¿otra variante?
    if (intent === 'create_part' && phase === 'ask_more_variante') {
      const lower = userInput.toLowerCase()
      if (lower.includes('si') || lower.includes('sí') || lower.includes('otra') || lower.includes('agregar')) {
        data['_current_variante'] = {}
        data['_phase'] = 'otra_variante'
        step++
        saveState(convId, intent, data, step)
        const variantes = (data['variantes'] as unknown[]) ?? []
        const suggested = `${String(data['codigo'] ?? 'P').toUpperCase()}-${String(variantes.length + 2).padStart(2, '0')}`
        return c.json({
          data: {
            conversation_id: convId,
            status: 'clarify',
            message: `Variante ${variantes.length + 2}. ¿Cuál es el código de la variante? (se sugiere ${suggested})`,
            data,
            suggestions: [{ label: suggested, value: suggested }],
            guided_state: { intent, data, step },
          },
        })
      }
      // Finalizar: guardar
      data['_phase'] = 'done'
      const result = await savePart(supabase, companyId, data)
      if (result.success) {
        clearState(convId)
        return c.json({
          data: {
            conversation_id: convId,
            status: 'saved',
            message: result.message,
            data: result.data,
            suggestions: [{ label: 'Crear otra parte', value: 'crear otra parte' }, { label: 'Volver al menú', value: 'menu' }],
            guided_state: null,
          },
        })
      }
      return c.json({
        data: {
          conversation_id: convId,
          status: 'clarify',
          message: `No pude guardar: ${result.message}. ¿Querés corregir algún dato?`,
          data,
          suggestions: [{ label: 'Reintentar', value: 'reintentar' }, { label: 'Cancelar', value: 'cancelar' }],
          guided_state: { intent, data, step },
        },
      })
    }

    const next = nextMissingField(intent, data)
    if (next === null) {
      // datos completos → preview
      return c.json({
        data: {
          conversation_id: convId,
          status: 'preview',
          message: 'Datos completos. ¿Confirmamos y guardamos?',
          data,
          suggestions: [{ label: 'Confirmar y guardar', value: 'confirmar' }, { label: 'Corregir', value: 'corregir' }],
          guided_state: { intent, data, step },
        },
      })
    }

    const field = next.field
    const effectiveInput = fieldValue !== null && LOOKUP_FIELDS.includes(field) ? fieldValue : userInput
    const isSkip = effectiveInput === '(omitir)' || effectiveInput.trim() === ''

    // BOM: manejo de componentes
    if (intent === 'create_bom') {
      if (field === 'parent_part') {
        const code = extractCode(effectiveInput)
        if (!code) {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: 'No entendí el código. ¿Cuál es la pieza padre?', data, suggestions: [], guided_state: { intent, data, step } } })
        }
        data['parent_part'] = code
        data['_current_component'] = {}
        step++
        saveState(convId, intent, data, step)
        const nxt = nextMissingField(intent, data)
        return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt?.message ?? '', data, suggestions: nxt?.suggestions ?? [], lookup: nxt?.lookup ?? null, guided_state: { intent, data, step } } })
      }
      if (field === 'component_code') {
        const code = extractCode(effectiveInput)
        if (!code) {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: 'No entendí el código del componente.', data, suggestions: [], guided_state: { intent, data, step } } })
        }
        ;(data['_current_component'] as Record<string, unknown>)['code'] = code
        step++
        saveState(convId, intent, data, step)
        const nxt = nextMissingField(intent, data)
        return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt?.message ?? '', data, suggestions: nxt?.suggestions ?? [], lookup: nxt?.lookup ?? null, guided_state: { intent, data, step } } })
      }
      if (field === 'component_qty') {
        const qty = extractPositiveNumber(effectiveInput)
        if (qty === null) {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: 'La cantidad debe ser un número positivo.', data, suggestions: [], guided_state: { intent, data, step } } })
        }
        ;(data['_current_component'] as Record<string, unknown>)['quantity'] = qty
        step++
        saveState(convId, intent, data, step)
        const nxt = nextMissingField(intent, data)
        return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt?.message ?? '', data, suggestions: nxt?.suggestions ?? [], lookup: nxt?.lookup ?? null, guided_state: { intent, data, step } } })
      }
      if (field === 'add_more') {
        const lower = effectiveInput.toLowerCase()
        if (lower.includes('si') || lower.includes('sí') || lower.includes('otro') || lower.includes('agregar')) {
          const components = (data['components'] as Array<Record<string, unknown>>) ?? []
          components.push({ ...((data['_current_component'] as Record<string, unknown>) ?? {}) })
          data['components'] = components
          data['_current_component'] = {}
          step++
          saveState(convId, intent, data, step)
          const nxt = nextMissingField(intent, data)
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt?.message ?? '', data, suggestions: nxt?.suggestions ?? [], lookup: nxt?.lookup ?? null, guided_state: { intent, data, step } } })
        }
        // finalizar → preview
        const components = (data['components'] as Array<Record<string, unknown>>) ?? []
        components.push({ ...((data['_current_component'] as Record<string, unknown>) ?? {}) })
        data['components'] = components
        data['_current_component'] = {}
        step++
        saveState(convId, intent, data, step)
        return c.json({
          data: {
            conversation_id: convId,
            status: 'preview',
            message: `BOM con ${components.length} componente(s). ¿Confirmamos y guardamos?`,
            data,
            suggestions: [{ label: 'Confirmar y guardar', value: 'confirmar' }, { label: 'Corregir', value: 'corregir' }],
            guided_state: { intent, data, step },
          },
        })
      }
    }

    // Parte: campos de variante
    if (intent === 'create_part' && (phase === 'variante' || phase === 'otra_variante')) {
      const current = (data['_current_variante'] as Record<string, unknown>) ?? {}
      if (isSkip && field === 'codigo_variante') {
        const variantes = (data['variantes'] as unknown[]) ?? []
        const suggested = `${String(data['codigo'] ?? 'P').toUpperCase()}-${String(variantes.length + 1).padStart(2, '0')}`
        current['codigo_variante'] = suggested
      } else if (isSkip) {
        const def = getDefaultForField(field, data)
        if (def !== null) {
          current[field] = def
        } else {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: `Este dato es obligatorio. ${next.message}`, data, suggestions: next.suggestions, guided_state: { intent, data, step } } })
        }
      } else {
        const value = extractFieldValue(field, effectiveInput, intent)
        if (value === null) {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: `No entendí bien ese dato. ${next.message}`, data, suggestions: next.suggestions, guided_state: { intent, data, step } } })
        }
        current[field] = value
        if (fieldLabel && LOOKUP_FIELDS.includes(field)) {
          current['_label_' + field] = fieldLabel
        }
      }
      data['_current_variante'] = current
      step++
      saveState(convId, intent, data, step)

      // ¿variante completa?
      const nxt = nextMissingField(intent, data)
      if (nxt === null || nxt.field === 'add_more_variante') {
        const variantes = (data['variantes'] as unknown[]) ?? []
        variantes.push(current)
        data['variantes'] = variantes
        data['_current_variante'] = {}
        data['_phase'] = 'ask_more_variante'
        step++
        saveState(convId, intent, data, step)
        return c.json({
          data: {
            conversation_id: convId,
            status: 'clarify',
            message: 'Variante completada. ¿Querés agregar otra variante o finalizar?',
            data,
            suggestions: [{ label: 'Agregar otra variante', value: 'si' }, { label: 'Finalizar', value: 'no' }],
            guided_state: { intent, data, step },
          },
        })
      }
      return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt.message, data, suggestions: nxt.suggestions, lookup: nxt.lookup ?? null, guided_state: { intent, data, step } } })
    }

    // Parte: campos de parte
    if (intent === 'create_part' && phase === 'parte') {
      if (isSkip) {
        const def = getDefaultForField(field, data)
        if (def !== null) {
          data[field] = def
          step++
          saveState(convId, intent, data, step)
          const nxt = nextMissingField(intent, data)
          if (nxt === null || nxt.field === 'codigo_variante') {
            data['_phase'] = 'variante'
            data['_current_variante'] = {}
            step++
            saveState(convId, intent, data, step)
            const nxt2 = nextMissingField(intent, data)
            return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt2?.message ?? '', data, suggestions: nxt2?.suggestions ?? [], lookup: nxt2?.lookup ?? null, guided_state: { intent, data, step } } })
          }
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt.message, data, suggestions: nxt.suggestions, lookup: nxt.lookup ?? null, guided_state: { intent, data, step } } })
        }
        return c.json({ data: { conversation_id: convId, status: 'clarify', message: `Este dato es obligatorio. ${next.message}`, data, suggestions: next.suggestions, guided_state: { intent, data, step } } })
      }

      const value = extractFieldValue(field, effectiveInput, intent)
      if (value === null) {
        return c.json({ data: { conversation_id: convId, status: 'clarify', message: `No entendí bien ese dato. ${next.message}`, data, suggestions: next.suggestions, guided_state: { intent, data, step } } })
      }

      // duplicado de código
      if (field === 'codigo') {
        const { data: dup } = await supabase.from('partes').select('id').eq('codigo', String(value).toUpperCase()).limit(1).maybeSingle()
        if (dup) {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: `El código "${value}" ya existe en otra parte. Elegí un código diferente.`, data, suggestions: [], guided_state: { intent, data, step } } })
        }
      }

      data[field] = value
      if (fieldLabel && LOOKUP_FIELDS.includes(field)) {
        data['_label_' + field] = fieldLabel
      }
      step++
      saveState(convId, intent, data, step)

      const nxt = nextMissingField(intent, data)
      if (nxt === null || nxt.field === 'codigo_variante') {
        data['_phase'] = 'variante'
        data['_current_variante'] = {}
        step++
        saveState(convId, intent, data, step)
        const nxt2 = nextMissingField(intent, data)
        return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt2?.message ?? '', data, suggestions: nxt2?.suggestions ?? [], lookup: nxt2?.lookup ?? null, guided_state: { intent, data, step } } })
      }
      return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt.message, data, suggestions: nxt.suggestions, lookup: nxt.lookup ?? null, guided_state: { intent, data, step } } })
    }

    // Supplier: campos genéricos
    if (intent === 'create_supplier') {
      if (isSkip) {
        const def = getDefaultForField(field, data)
        if (def !== null) {
          data[field] = def
        } else if (field === 'identificacion_tributaria' || field === 'contacto_email' || field === 'contacto_telefono' || field === 'direccion') {
          data[field] = null
        } else {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: `Este dato es obligatorio. ${next.message}`, data, suggestions: next.suggestions, guided_state: { intent, data, step } } })
        }
      } else {
        const value = extractFieldValue(field, effectiveInput, intent)
        if (value === null) {
          return c.json({ data: { conversation_id: convId, status: 'clarify', message: `No entendí bien ese dato. ${next.message}`, data, suggestions: next.suggestions, guided_state: { intent, data, step } } })
        }
        data[field] = value
        if (fieldLabel && LOOKUP_FIELDS.includes(field)) {
          data['_label_' + field] = fieldLabel
        }
      }
      step++
      saveState(convId, intent, data, step)
      const nxt = nextMissingField(intent, data)
      if (nxt === null) {
        return c.json({
          data: {
            conversation_id: convId,
            status: 'preview',
            message: 'Datos completos. ¿Confirmamos y guardamos?',
            data,
            suggestions: [{ label: 'Confirmar y guardar', value: 'confirmar' }, { label: 'Corregir', value: 'corregir' }],
            guided_state: { intent, data, step },
          },
        })
      }
      return c.json({ data: { conversation_id: convId, status: 'clarify', message: nxt.message, data, suggestions: nxt.suggestions, lookup: nxt.lookup ?? null, guided_state: { intent, data, step } } })
    }
  }

  // general_query: respuesta offline (sin API key) o vía Ollama
  const apiKey = process.env.AGENT_AI_API_KEY
  if (apiKey) {
    try {
      const endpoint = process.env.AGENT_AI_API_ENDPOINT ?? 'https://ollama.com/v1/chat/completions'
      const model = process.env.AGENT_AI_API_MODEL ?? 'deepseek-v4-flash:0731'
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${apiKey}` },
        body: JSON.stringify({
          model,
          messages: [
            { role: 'system', content: 'You are an MRP assistant. ALWAYS reply with ONLY a valid JSON object, no text, no markdown, no backticks. Strict format: {"status":"response","message":"...","data":null,"suggestions":["..."]}.' },
            { role: 'user', content: userInput },
          ],
          temperature: 0.1,
          max_tokens: 800,
        }),
      })
      if (res.ok) {
        const data = await res.json()
        const content = data?.choices?.[0]?.message?.content ?? ''
        const jsonMatch = content.match(/\{.*\}/s)
        if (jsonMatch) {
          const parsed = JSON.parse(jsonMatch[0])
          return c.json({ data: { conversation_id: convId, status: parsed.status ?? 'response', message: parsed.message ?? content, data: parsed.data ?? null, suggestions: parsed.suggestions ?? [], guided_state: null } })
        }
        return c.json({ data: { conversation_id: convId, status: 'response', message: content, data: null, suggestions: [], guided_state: null } })
      }
    } catch {
      // fallback offline
    }
  }

  return c.json({
    data: {
      conversation_id: convId,
      status: 'response',
      message: 'Soy el asistente de MRP. Puedo ayudarte a crear partes, armar listas de materiales (BOM) o registrar clientes y proveedores. Elegí una opción del menú para comenzar.',
      data: null,
      suggestions: SUGGESTIONS.map((s) => ({ label: s.label, value: s.intent })),
      guided_state: null,
    },
  })
})

agent.post('/confirm', async (c) => {
  const body = await c.req.json().catch(() => null)
  const convId = String(body?.conversation_id ?? '')
  const intent = String(body?.intent ?? '')
  const data = (body?.data ?? {}) as Record<string, unknown>
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const companyId = c.get('companyId')

  let result: { success: boolean; message: string; data?: unknown }
  if (intent === 'create_part') {
    result = await savePart(supabase, companyId, data)
  } else if (intent === 'create_supplier') {
    result = await saveSupplier(supabase, companyId, data)
  } else if (intent === 'create_bom') {
    result = await saveBom(supabase, companyId, data)
  } else {
    result = { success: false, message: `Intent no soportado: ${intent}` }
  }

  if (result.success) clearState(convId)
  return c.json({ data: result })
})

agent.post('/cancel', async (c) => {
  const body = await c.req.json().catch(() => null)
  const convId = String(body?.conversation_id ?? '')
  if (convId) clearState(convId)
  return c.json({ data: { success: true, message: 'Operación cancelada.', guided_state: null } })
})
