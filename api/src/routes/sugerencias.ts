import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

// ─── Sugerencias MRP ────────────────────────────────────────────────────────
// Port de SugerenciasService.php: para cada BOM activa calcula ratio
// stock/cantidad por componente; status = fabricable | parcial | sin_stock

export const sugerencias = new Hono<AuthEnv>()
sugerencias.use('*', requireAuth)

const FILTROS = ['fabricable', 'parcial', 'sin_stock'] as const

sugerencias.get('/', async (c) => {
  const filtro = c.req.query('filtro')
  const filtroValido = FILTROS.includes(filtro as (typeof FILTROS)[number])
    ? (filtro as (typeof FILTROS)[number])
    : null

  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data: filasRaw, error } = await supabase
    .from('bom_cabecera')
    .select(
      `id,
       variante_padre_id,
       variantes_padre:variantes!bom_cabecera_variante_padre_id_fkey(
         id, codigo_variante, detalle, stock_actual,
         partes!variantes_id_parte_fkey(id, codigo, detalle)
       ),
       bom_detalle(
         id, variante_componente_id, cantidad_necesaria, secuencia, unidad_medida_id,
         variantes_componente:variantes!bom_detalle_variante_componente_id_fkey(
           id, codigo_variante, detalle, stock_actual,
           partes!variantes_id_parte_fkey(id, codigo, detalle)
         ),
         unidades_medida!bom_detalle_unidad_medida_id_fkey(simbolo)
       )`
    )
    .eq('activa', true)
    .order('id', { ascending: false })

  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  const resumen = { fabricables: 0, parciales: 0, sin_stock: 0, total: 0 }
  const variantes: Array<Record<string, unknown>> = []

  const filas = (filasRaw ?? []) as any[]
  for (const bom of filas) {
    const padre = bom.variantes_padre
    const detalles = bom.bom_detalle ?? []
    if (!padre || detalles.length === 0) continue

    const componentes = detalles.map((d: any) => {
      const comp = d.variantes_componente
      const qty = Number(d.cantidad_necesaria ?? 0)
      const stock = Number(comp?.stock_actual ?? 0)
      const ratio = qty > 0 ? stock / qty : 0
      return {
        comp_variante_id: d.variante_componente_id,
        comp_parte_codigo: comp?.partes?.codigo ?? '',
        comp_codigo: comp?.codigo_variante ?? '',
        comp_detalle: comp?.detalle ?? '',
        cantidad_necesaria: qty,
        stock_disponible: stock,
        unidad_simbolo: d.unidades_medida?.simbolo ?? 'UN',
        ratio,
        cobertura_pct: Math.min(Math.round(ratio * 1000) / 10, 100),
        secuencia: d.secuencia ?? 0,
      }
    })

    const minRatio = Math.min(...componentes.map((c: { ratio: number }) => c.ratio))
    const maxUnidades = Math.floor(Math.min(...componentes.map((c: { ratio: number }) => c.ratio)))
    const status = minRatio >= 1 ? 'fabricable' : minRatio > 0 ? 'parcial' : 'sin_stock'
    const coberturaPct = Math.min(Math.round(minRatio * 1000) / 10, 100)

    if (filtroValido && status !== filtroValido) continue

    resumen[status === 'fabricable' ? 'fabricables' : status === 'parcial' ? 'parciales' : 'sin_stock']++
    resumen.total++

    variantes.push({
      bom_id: bom.id,
      variante_id: bom.variante_padre_id,
      parte_codigo: padre.partes?.codigo ?? '',
      parte_detalle: padre.partes?.detalle ?? '',
      variante_codigo: padre.codigo_variante,
      variante_detalle: padre.detalle,
      status,
      cobertura_pct: coberturaPct,
      max_unidades: Number.isFinite(maxUnidades) ? maxUnidades : 0,
      componentes,
    })
  }

  return c.json({ data: { resumen, variantes } })
})

// ─── Stock crítico ───────────────────────────────────────────────────────────
// Port de Variante::getStockCritico + getStockCriticoStats

export const inventario = new Hono<AuthEnv>()
inventario.use('*', requireAuth)

const ESTADOS = ['todos', 'critico', 'advertencia', 'normal'] as const

inventario.get('/critico', async (c) => {
  const estado = c.req.query('estado')
  const estadoValido = ESTADOS.includes(estado as (typeof ESTADOS)[number])
    ? (estado as (typeof ESTADOS)[number])
    : 'todos'

  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const { data: variantesRaw, error } = await supabase
    .from('variantes')
    .select(
      `id, codigo_variante, detalle, stock_actual, punto_pedido, stock_seguridad,
       lote_minimo, estado,
       partes!variantes_id_parte_fkey(id, codigo, detalle, id_tipo, id_grupo, id_um_uso,
         tipos_partes!partes_id_tipo_fkey(nombre),
         grupos_partes!partes_id_grupo_fkey(nombre, color),
         unidades_medida!partes_id_um_uso_fkey(simbolo))`
    )
    .eq('estado', 'activa')

  if (error) return c.json({ error: { code: 'DB_ERROR', message: error.message } }, 500)

  const items = ((variantesRaw ?? []) as any[])
    .map((v: any) => {
      const stock = Number(v.stock_actual ?? 0)
      const punto = Number(v.punto_pedido ?? 0)
      const estadoStock =
        stock === 0 || stock < punto ? 'critico' : stock < punto * 1.5 ? 'advertencia' : 'normal'
      return {
        id: v.id,
        codigo_variante: v.codigo_variante,
        detalle: v.detalle,
        stock_actual: stock,
        punto_pedido: punto,
        stock_seguridad: Number(v.stock_seguridad ?? 0),
        lote_minimo: Number(v.lote_minimo ?? 0),
        codigo_parte: v.partes?.codigo ?? '',
        detalle_parte: v.partes?.detalle ?? '',
        tipo_nombre: v.partes?.tipos_partes?.nombre ?? '',
        grupo_nombre: v.partes?.grupos_partes?.nombre ?? '',
        grupo_color: v.partes?.grupos_partes?.color ?? '',
        unidad_medida: v.partes?.unidades_medida?.simbolo ?? 'UN',
        estado_stock: estadoStock,
        faltante: Math.max(0, punto - stock),
      }
    })
    .filter((v: any) => estadoValido === 'todos' || v.estado_stock === estadoValido)
    .sort((a: any, b: any) => {
      const orden = { critico: 1, advertencia: 2, normal: 3 } as Record<string, number>
      return (orden[a.estado_stock] ?? 3) - (orden[b.estado_stock] ?? 3)
    })

  const stats = {
    total: items.length,
    critico: items.filter((v: any) => v.estado_stock === 'critico').length,
    advertencia: items.filter((v: any) => v.estado_stock === 'advertencia').length,
    normal: items.filter((v: any) => v.estado_stock === 'normal').length,
  }

  return c.json({ data: { items, stats } })
})
