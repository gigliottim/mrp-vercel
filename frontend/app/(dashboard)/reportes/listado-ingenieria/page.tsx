import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export const dynamic = 'force-dynamic'

type Variante = { id: number; codigo_variante: string; detalle: string }
type Tipo = { id: number; nombre: string; codigo: string }
type Item = { nivel?: number; codigo_variante?: string; variante_detalle?: string; cantidad_ajustada?: number; cantidad?: number; cantidad_necesaria?: number; cantidad_total?: number; unidad?: string; unidades_medida?: { simbolo: string }; variantes_componente?: { codigo_variante: string; detalle: string }; tipo_codigo?: string; precio_unitario?: number; subtotal?: number }

export default async function ListadoIngenieriaPage({ searchParams }: { searchParams: Promise<{ id_variante?: string; cantidad?: string; tipo_salida?: string; con_precios?: string; agrupar_tipo?: string; ordenar_tipo?: string; mostrar_tipos?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const varianteId = Number(sp.id_variante ?? 0)
  const cantidad = Number(sp.cantidad ?? 1) || 1
  const tipoSalida = sp.tipo_salida ?? 'arbol'
  const conPrecios = sp.con_precios === '1'
  const agruparTipo = sp.agrupar_tipo === '1'
  const ordenarTipo = sp.ordenar_tipo === '1'
  const mostrarTipos = (sp.mostrar_tipos ?? '').split(',').map(Number).filter((n) => n > 0)

  const res = varianteId > 0
    ? await apiFetch<{ data: { variantes: Variante[]; variante_seleccionada: Variante | null; tipos: Tipo[]; items: Item[] | Array<{ tipo: string; items: Item[] }> } }>(
        `/api/v1/reportes/listado-ingenieria?id_variante=${varianteId}&cantidad=${cantidad}&tipo_salida=${tipoSalida}${conPrecios ? '&con_precios=1' : ''}${agruparTipo ? '&agrupar_tipo=1' : ''}${ordenarTipo ? '&ordenar_tipo=1' : ''}${mostrarTipos.length > 0 ? `&mostrar_tipos=${mostrarTipos.join(',')}` : ''}`,
        session.accessToken
      ).catch(() => null)
    : null

  const variantes = res?.data.variantes ?? []
  const tipos = res?.data.tipos ?? []
  const items = res?.data.items ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Listado de Ingeniería</h1>
        <p className="text-sm text-muted-foreground">Explosión de BOM con cantidad, filtros y precios</p>
      </div>

      <form method="get" className="flex flex-wrap items-end gap-4">
        <div className="w-72 space-y-2">
          <label className="text-sm font-medium">Variante</label>
          <select name="id_variante" defaultValue={varianteId || ''} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="">Seleccionar...</option>
            {variantes.map((v) => <option key={v.id} value={v.id}>{v.codigo_variante} — {v.detalle}</option>)}
          </select>
        </div>
        <div className="w-24 space-y-2">
          <label className="text-sm font-medium">Cantidad</label>
          <input name="cantidad" type="number" step="any" min={0} defaultValue={cantidad} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
        </div>
        <div className="w-40 space-y-2">
          <label className="text-sm font-medium">Tipo de salida</label>
          <select name="tipo_salida" defaultValue={tipoSalida} className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
            <option value="arbol">Árbol</option>
            <option value="plana">Plana</option>
            <option value="rama1">Rama 1</option>
          </select>
        </div>
        <label className="flex items-center gap-2 pb-2.5 text-sm">
          <input type="checkbox" name="con_precios" value="1" defaultChecked={conPrecios} className="h-4 w-4" /> Con precios
        </label>
        <label className="flex items-center gap-2 pb-2.5 text-sm">
          <input type="checkbox" name="agrupar_tipo" value="1" defaultChecked={agruparTipo} className="h-4 w-4" /> Agrupar por tipo
        </label>
        <label className="flex items-center gap-2 pb-2.5 text-sm">
          <input type="checkbox" name="ordenar_tipo" value="1" defaultChecked={ordenarTipo} className="h-4 w-4" /> Ordenar por tipo
        </label>
        <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">Generar</button>
      </form>

      {tipos.length > 0 ? (
        <div className="rounded-md border p-4">
          <p className="mb-2 text-sm font-medium">Filtrar por tipo de parte:</p>
          <div className="flex flex-wrap gap-2">
            {tipos.map((t) => (
              <label key={t.id} className="flex items-center gap-1.5 text-sm">
                <input
                  type="checkbox"
                  name="mostrar_tipos"
                  value={t.id}
                  defaultChecked={mostrarTipos.length === 0 || mostrarTipos.includes(t.id)}
                  className="h-4 w-4"
                />
                {t.nombre}
              </label>
            ))}
          </div>
        </div>
      ) : null}

      {res?.data ? (
        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50 text-left">
                <th className="p-2 font-medium">Código</th>
                <th className="p-2 font-medium">Detalle</th>
                <th className="p-2 text-right font-medium">Cantidad</th>
                <th className="p-2 font-medium">Unidad</th>
                {conPrecios ? <th className="p-2 text-right font-medium">Precio</th> : null}
                {conPrecios ? <th className="p-2 text-right font-medium">Subtotal</th> : null}
              </tr>
            </thead>
            <tbody>
              {Array.isArray(items) && !('tipo' in (items[0] ?? {}))
                ? (items as Item[]).map((r, i) => (
                    <tr key={i} className="border-b">
                      <td className="p-2" style={{ paddingLeft: `${12 + (r.nivel ?? 0) * 20}px` }}>
                        <code>{r.codigo_variante ?? r.variantes_componente?.codigo_variante ?? ''}</code>
                      </td>
                      <td className="p-2">{r.variante_detalle ?? r.variantes_componente?.detalle ?? ''}</td>
                      <td className="p-2 text-right font-mono">{r.cantidad_ajustada ?? r.cantidad ?? r.cantidad_necesaria ?? r.cantidad_total ?? 0}</td>
                      <td className="p-2">{r.unidad ?? r.unidades_medida?.simbolo ?? ''}</td>
                      {conPrecios ? <td className="p-2 text-right font-mono">{r.precio_unitario ?? 0}</td> : null}
                      {conPrecios ? <td className="p-2 text-right font-mono">{r.subtotal ?? 0}</td> : null}
                    </tr>
                  ))
                : (items as Array<{ tipo: string; items: Item[] }>).map((grupo) => (
                    <Fragment key={grupo.tipo}>
                      <tr className="border-b bg-muted/30">
                        <td colSpan={6} className="p-2 font-semibold">{grupo.tipo}</td>
                      </tr>
                      {grupo.items.map((r, i) => (
                        <tr key={i} className="border-b">
                          <td className="p-2"><code>{r.codigo_variante ?? r.variantes_componente?.codigo_variante ?? ''}</code></td>
                          <td className="p-2">{r.variante_detalle ?? r.variantes_componente?.detalle ?? ''}</td>
                          <td className="p-2 text-right font-mono">{r.cantidad_ajustada ?? r.cantidad ?? r.cantidad_necesaria ?? r.cantidad_total ?? 0}</td>
                          <td className="p-2">{r.unidad ?? r.unidades_medida?.simbolo ?? ''}</td>
                          {conPrecios ? <td className="p-2 text-right font-mono">{r.precio_unitario ?? 0}</td> : null}
                          {conPrecios ? <td className="p-2 text-right font-mono">{r.subtotal ?? 0}</td> : null}
                        </tr>
                      ))}
                    </Fragment>
                  ))}
              {items.length === 0 ? (
                <tr><td colSpan={6} className="h-16 text-center text-muted-foreground">Seleccioná una variante para generar el listado</td></tr>
              ) : null}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  )
}

import { Fragment } from 'react'
