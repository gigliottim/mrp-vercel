import { Hono } from 'hono'
import { z } from 'zod'
import { requireAuth, type AuthEnv } from '../middleware/auth.js'
import { createUserClient } from '../lib/supabase.js'

// ─── Reportes ────────────────────────────────────────────────────────────────
// Port de ReportesController.php: destino-partes, listado-ingenieria,
// planificacion-produccion, resumen-grupos

export const reportes = new Hono<AuthEnv>()
reportes.use('*', requireAuth)

// Helper: variantes con partes (allWithPartes)
async function fetchVariantes(supabase: ReturnType<typeof createUserClient>) {
  const { data, error } = await supabase
    .from('variantes')
    .select(
      `id, codigo_variante, detalle, stock_actual, punto_pedido, lote_minimo, estado,
       partes!variantes_id_parte_fkey(id, codigo, detalle, id_tipo, id_grupo, id_um_uso, id_um_compra,
         tipos_partes!partes_id_tipo_fkey(id, nombre, codigo, requiere_stock),
         grupos_partes!partes_id_grupo_fkey(id, nombre, color),
         um_uso:unidades_medida!partes_id_um_uso_fkey(simbolo),
         um_compra:unidades_medida!partes_id_um_compra_fkey(simbolo))`
    )
    .order('id')
  if (error) throw new Error(error.message)
  return (data ?? []) as any[]
}

// Helper: BOM activa de una variante
async function fetchBomActiva(supabase: ReturnType<typeof createUserClient>, varianteId: number) {
  const { data } = await supabase
    .from('bom_cabecera')
    .select('id')
    .eq('variante_padre_id', varianteId)
    .eq('activa', true)
    .order('created_at', { ascending: false })
    .limit(1)
    .maybeSingle()
  return data
}

// Helper: detalles de BOM con joins
async function fetchDetalles(supabase: ReturnType<typeof createUserClient>, bomId: number) {
  const { data, error } = await supabase
    .from('bom_detalle')
    .select(
      `id, variante_componente_id, cantidad_necesaria, unidad_medida_id, secuencia,
       variantes_componente:variantes!bom_detalle_variante_componente_id_fkey(
         id, codigo_variante, detalle, stock_actual,
         partes!variantes_id_parte_fkey(id, codigo, detalle, id_tipo,
           tipos_partes!partes_id_tipo_fkey(id, nombre, codigo))
       ),
       unidades_medida!bom_detalle_unidad_medida_id_fkey(simbolo)`
    )
    .eq('bom_id', bomId)
    .order('secuencia')
  if (error) throw new Error(error.message)
  return (data ?? []) as any[]
}

// ─── Destino de Partes ───────────────────────────────────────────────────────

reportes.get('/destino-partes', async (c) => {
  const varianteId = Number(c.req.query('id_variante') ?? 0)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  const variantes = await fetchVariantes(supabase)
  const varianteSeleccionada = variantes.find((v: any) => Number(v.id) === varianteId) ?? null

  let rama1: unknown[] = []
  let arbol: unknown[] = []
  let plana: unknown[] = []
  let dondeSeUtiliza: unknown[] = []

  if (varianteSeleccionada) {
    const bom = await fetchBomActiva(supabase, varianteId)
    if (bom) {
      rama1 = await fetchDetalles(supabase, bom.id)
      const { data: tree } = await supabase.rpc('bom_tree', { p_variante_id: varianteId, p_max_depth: 5 })
      arbol = tree ?? []
      // Composición plana: consolidar por variante
      const consolidado = new Map<number, any>()
      for (const item of arbol as Array<any>) {
        if (Number(item.nivel) === 0) continue
        const vid = Number(item.variante_id)
        const prev = consolidado.get(vid)
        if (prev) {
          prev.cantidad_necesaria = Number(prev.cantidad_necesaria) + Number(item.cantidad)
        } else {
          consolidado.set(vid, {
            variante_id: vid,
            parte_codigo: item.parte_codigo ?? '',
            componente_codigo: item.codigo_variante ?? 'N/A',
            parte_detalle: item.parte_detalle ?? '',
            componente_detalle: item.variante_detalle ?? '',
            cantidad_necesaria: Number(item.cantidad),
            unidad_simbolo: item.unidad ?? 'UN',
          })
        }
      }
      plana = [...consolidado.values()]
    }
    const { data: whereUsed } = await supabase.rpc('bom_where_used', { p_variante_id: varianteId })
    dondeSeUtiliza = whereUsed ?? []
  }

  return c.json({
    data: {
      variantes,
      variante_seleccionada: varianteSeleccionada,
      rama1,
      plana,
      arbol,
      donde_se_utiliza: dondeSeUtiliza,
    },
  })
})

// ─── Listado de Ingeniería ───────────────────────────────────────────────────

reportes.get('/listado-ingenieria', async (c) => {
  const varianteId = Number(c.req.query('id_variante') ?? 0)
  const cantidad = Number(c.req.query('cantidad') ?? 1) || 1
  const tipoSalida = c.req.query('tipo_salida') ?? 'arbol'
  const conPrecios = c.req.query('con_precios') === '1'
  const agruparTipo = c.req.query('agrupar_tipo') === '1'
  const ordenarTipo = c.req.query('ordenar_tipo') === '1'
  const mostrarTipos = (c.req.query('mostrar_tipos') ?? '')
    .split(',')
    .map(Number)
    .filter((n) => n > 0)

  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const variantes = await fetchVariantes(supabase)
  const varianteSeleccionada = variantes.find((v: any) => Number(v.id) === varianteId) ?? null

  // Tipos de partes para filtros
  const { data: tiposData } = await supabase.from('tipos_partes').select('id, nombre, codigo').order('id')
  const tipos = tiposData ?? []

  let items: unknown[] = []
  if (varianteSeleccionada) {
    const bom = await fetchBomActiva(supabase, varianteId)
    if (bom) {
      if (tipoSalida === 'rama1') {
        const detalles = await fetchDetalles(supabase, bom.id)
        items = detalles.map((d: any) => ({
          ...d,
          cantidad_ajustada: Number(d.cantidad_necesaria) * cantidad,
          tipo_parte_id: d.variantes_componente?.partes?.tipos_partes?.id ?? null,
          tipo_codigo: d.variantes_componente?.partes?.tipos_partes?.codigo ?? '',
        }))
      } else {
        const { data: tree } = await supabase.rpc('bom_tree', { p_variante_id: varianteId, p_max_depth: 5 })
        const arbol = tree ?? []
        if (tipoSalida === 'plana') {
          const consolidado = new Map<number, any>()
          for (const item of arbol as Array<any>) {
            if (Number(item.nivel) === 0) continue
            const vid = Number(item.variante_id)
            const prev = consolidado.get(vid)
            if (prev) {
              prev.cantidad_total = Number(prev.cantidad_total) + Number(item.cantidad)
            } else {
              consolidado.set(vid, {
                variante_id: vid,
                parte_codigo: item.parte_codigo ?? '',
                parte_detalle: item.parte_detalle ?? '',
                codigo_variante: item.codigo_variante ?? 'N/A',
                variante_detalle: item.variante_detalle ?? '',
                tipo_codigo: item.tipo_codigo ?? '',
                tipo_parte_id: item.tipo_parte_id ?? null,
                unidad: item.unidad ?? 'UN',
                cantidad_total: Number(item.cantidad),
              })
            }
          }
          items = [...consolidado.values()].map((i) => ({
            ...i,
            cantidad_ajustada: Number(i.cantidad_total) * cantidad,
          }))
        } else {
          // arbol
          items = arbol.map((item: any) => ({
            ...item,
            cantidad_ajustada: Number(item.cantidad) * cantidad,
          }))
        }
      }

      // Filtrar por tipos
      if (mostrarTipos.length > 0) {
        items = items.filter((i: any) => {
          if (i.nivel === 0) return true
          return mostrarTipos.includes(Number(i.tipo_parte_id))
        })
      }

      // Ordenar por tipo
      if (ordenarTipo) {
        const tipoNombre = (id: any) => tipos.find((t: { id: number }) => t.id === Number(id))?.nombre ?? ''
        items.sort((a: any, b: any) =>
          tipoNombre(a.tipo_parte_id).localeCompare(tipoNombre(b.tipo_parte_id))
        )
      }

      // Precios (costo de última compra)
      if (conPrecios) {
        for (const item of items as any[]) {
          const vid = Number(item.variante_id ?? item.variante_componente_id ?? 0)
          const qty = Number(item.cantidad_ajustada ?? item.cantidad ?? item.cantidad_necesaria ?? item.cantidad_total ?? 0)
          let precio = 0
          if (vid > 0) {
            const { data: compra } = await supabase
              .from('compras_detalle')
              .select('precio_unitario')
              .eq('variante_id', vid)
              .order('created_at', { ascending: false })
              .limit(1)
              .maybeSingle()
            precio = Number(compra?.precio_unitario ?? 0)
          }
          item.precio_unitario = precio
          item.subtotal = precio * qty
        }
      }

      // Agrupar por tipo
      if (agruparTipo) {
        const agrupado: Record<string, unknown[]> = {}
        for (const item of items as any[]) {
          const tipoId = Number(item.tipo_parte_id ?? 0)
          const nombre = tipos.find((t: { id: number }) => t.id === tipoId)?.nombre ?? 'Sin Tipo'
          ;(agrupado[nombre] ??= []).push(item)
        }
        items = Object.entries(agrupado).map(([nombre, filas]) => ({ tipo: nombre, items: filas }))
      }
    }
  }

  return c.json({ data: { variantes, variante_seleccionada: varianteSeleccionada, tipos, items } })
})

// ─── Planificación de Producción ─────────────────────────────────────────────

const productosSchema = z.record(z.string(), z.coerce.number().positive())

reportes.get('/planificacion-produccion', async (c) => {
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))
  const fechaCosto = c.req.query('fecha_costo') ?? new Date().toISOString().slice(0, 10)

  // productos: "VID:QTY,VID2:QTY2"
  const productosRaw = c.req.query('productos') ?? ''
  const productos: Record<string, number> = {}
  for (const par of productosRaw.split(',').filter(Boolean)) {
    const [vid, qty] = par.split(':')
    if (vid && qty) productos[vid] = Number(qty)
  }

  const variantes = await fetchVariantes(supabase)
  const requerimientos: Array<any> = []
  const consolidado = new Map<number, any>()

  for (const [vid, qty] of Object.entries(productos)) {
    const { data: tree } = await supabase.rpc('bom_tree', { p_variante_id: Number(vid), p_max_depth: 5 })
    for (const item of (tree ?? []) as any[]) {
      if (Number(item.nivel) === 0) continue
      const compId = Number(item.variante_id)
      const cantidad = Number(item.cantidad) * qty
      const prev = consolidado.get(compId)
      if (prev) {
        prev.programado = Number(prev.programado) + cantidad
      } else {
        const info = variantes.find((v: any) => Number(v.id) === compId)
        consolidado.set(compId, {
          variante_id: compId,
          parte_id: info?.partes?.id ?? 0,
          codigo: item.codigo_variante ?? 'N/A',
          parte_codigo: info?.partes?.codigo ?? '',
          detalle: item.variante_detalle ?? '',
          unidad: item.unidad ?? 'UN',
          um_compra: info?.partes?.um_compra?.simbolo ?? item.unidad ?? 'UN',
          um_uso: info?.partes?.um_uso?.simbolo ?? item.unidad ?? 'UN',
          tipo: item.tipo_codigo ?? '',
          programado: cantidad,
          stock: Number(info?.stock_actual ?? 0),
          lote_minimo: Number(info?.lote_minimo ?? 1) || 1,
          factor_conversion: 1,
        })
      }
    }
  }

  for (const item of consolidado.values()) {
    const requiereStock = true // tipos sin requiere_stock se ignoran (default true)
    const faltante = requiereStock ? Math.max(0, Number(item.programado) - Number(item.stock)) : 0
    item.faltante = faltante
    if (faltante > 0) {
      const lote = Number(item.lote_minimo) || 1
      const factor = Number(item.factor_conversion) || 1
      const aComprarUso = Math.ceil(faltante / lote) * lote
      item.a_comprar = Math.ceil(aComprarUso / factor)
      item.a_comprar_uso = aComprarUso
      item.a_comprar_um = item.um_compra
      item.stock_final = Number(item.stock) + aComprarUso - Number(item.programado)
    } else {
      item.a_comprar = 0
      item.a_comprar_uso = 0
      item.a_comprar_um = item.um_compra
      item.stock_final = Number(item.stock) - Number(item.programado)
    }
    // Precio de última compra
    const { data: compra } = await supabase
      .from('compras_detalle')
      .select('precio_unitario')
      .eq('variante_id', Number(item.variante_id))
      .order('created_at', { ascending: false })
      .limit(1)
      .maybeSingle()
    item.precio_unitario = Number(compra?.precio_unitario ?? 0)
    item.a_comprar_precio = Number(item.a_comprar_uso) * Number(item.precio_unitario)
    requerimientos.push(item)
  }

  requerimientos.sort((a, b) => String(a.codigo).localeCompare(String(b.codigo)))

  return c.json({ data: { variantes, productos, requerimientos, fecha_costo: fechaCosto } })
})

// ─── Resumen por Grupos ──────────────────────────────────────────────────────

reportes.get('/resumen-grupos', async (c) => {
  const grupoId = Number(c.req.query('id_grupo') ?? 0)
  const supabase = createUserClient(c.req.header('Authorization')!.slice(7))

  // Grupos con conteo de partes
  const { data: gruposData } = await supabase
    .from('grupos_partes')
    .select('id, nombre, color, partes(id)')
    .order('nombre')
  const grupos = (gruposData ?? []).map((g: any) => ({
    id: g.id,
    nombre: g.nombre,
    color: g.color,
    cantidad_partes: (g.partes as unknown[])?.length ?? 0,
  }))

  let grupoSeleccionado: any | null = null
  let partesDelGrupo: unknown[] = []
  let sinGrupo: unknown[] = []

  if (grupoId > 0) {
    grupoSeleccionado = grupos.find((g: any) => Number(g.id) === grupoId) ?? null
    if (grupoSeleccionado) {
      const { data: partes } = await supabase
        .from('partes')
        .select(
          `id, codigo, detalle, id_tipo, id_grupo,
           tipos_partes!partes_id_tipo_fkey(nombre, codigo),
           variantes!partes_id_fkey(id, codigo_variante, detalle, estado, stock_actual, punto_pedido, lote_minimo,
             unidades_medida!variantes_id_um_fkey(simbolo))`
        )
        .eq('id_grupo', grupoId)
        .order('codigo')
      const agrupado = new Map<number, any>()
      for (const p of (partes ?? []) as Array<any>) {
        const pid = Number(p.id)
        const prev = agrupado.get(pid)
        if (!prev) {
          agrupado.set(pid, {
            parte_id: pid,
            parte_codigo: p.codigo,
            parte_detalle: p.detalle,
            tipo_nombre: p.tipos_partes?.nombre ?? '',
            tipo_codigo: p.tipos_partes?.codigo ?? '',
            variantes: [],
          })
        }
        for (const v of (p.variantes ?? []) as any[]) {
          const stock = Number(v.stock_actual ?? 0)
          const punto = Number(v.punto_pedido ?? 0)
          agrupado.get(pid)!.variantes.push({
            variante_id: v.id,
            codigo_variante: v.codigo_variante,
            variante_detalle: v.detalle,
            variante_estado: v.estado,
            stock_actual: stock,
            punto_pedido: punto,
            lote_minimo: Number(v.lote_minimo ?? 0),
            unidad_medida: v.unidades_medida?.simbolo ?? 'UN',
            estado_stock: stock === 0 || stock < punto ? 'critico' : stock < punto * 1.5 ? 'advertencia' : 'normal',
          })
        }
      }
      partesDelGrupo = [...agrupado.values()]
    }
  }

  // Partes sin grupo
  const { data: sinGrupoData } = await supabase
    .from('partes')
    .select(
      `id, codigo, detalle, id_tipo,
       tipos_partes!partes_id_tipo_fkey(nombre),
       variantes!partes_id_fkey(id, codigo_variante, detalle, estado, stock_actual, punto_pedido,
         unidades_medida!variantes_id_um_fkey(simbolo))`
    )
    .is('id_grupo', null)
    .order('codigo')
  const sinGrupoMap = new Map<number, any>()
  for (const p of (sinGrupoData ?? []) as any[]) {
    const pid = Number(p.id)
    const prev = sinGrupoMap.get(pid)
    if (!prev) {
      sinGrupoMap.set(pid, {
        parte_id: pid,
        parte_codigo: p.codigo,
        parte_detalle: p.detalle,
        tipo_nombre: p.tipos_partes?.nombre ?? '',
        variantes: [],
      })
    }
    for (const v of (p.variantes ?? []) as any[]) {
      sinGrupoMap.get(pid)!.variantes.push({
        codigo_variante: v.codigo_variante,
        variante_detalle: v.detalle,
        variante_estado: v.estado,
        stock_actual: Number(v.stock_actual ?? 0),
        punto_pedido: Number(v.punto_pedido ?? 0),
        unidad_medida: v.unidades_medida?.simbolo ?? 'UN',
      })
    }
  }
  sinGrupo = [...sinGrupoMap.values()]

  return c.json({
    data: {
      grupos,
      grupo_seleccionado: grupoSeleccionado,
      partes_del_grupo: partesDelGrupo,
      partes_sin_grupo: sinGrupo,
      count_sin_grupo: sinGrupo.length,
    },
  })
})
