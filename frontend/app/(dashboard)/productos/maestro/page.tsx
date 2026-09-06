import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'
import { MaestroForm, type VarianteOpt, type UmOpt } from './maestro-form'
import { agregarComponente, eliminarBom } from './actions'

export const dynamic = 'force-dynamic'

type TreeRow = {
  variante_id: number
  parte_codigo: string
  parte_detalle: string
  codigo_variante: string
  variante_detalle: string
  tipo_codigo: string | null
  parent_id: number | null
  bom_detalle_id: number | null
  cantidad: number
  unidad: string | null
  nivel: number
}

export default async function MaestroPage({ searchParams }: { searchParams: Promise<{ id_variante?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const varianteId = Number(sp.id_variante ?? 0)

  const [variantes, ums, tree] = await Promise.all([
    apiFetch<{ data: VarianteOpt[] }>('/api/v1/variantes?perPage=500', session.accessToken).catch(() => null),
    apiFetch<{ data: UmOpt[] }>('/api/v1/unidades-medida?perPage=100', session.accessToken).catch(() => null),
    varianteId > 0
      ? apiFetch<{ data: TreeRow[] }>(`/api/v1/bom/tree/${varianteId}`, session.accessToken).catch(() => null)
      : Promise.resolve(null),
  ])

  const canAdmin = session.role === 'Super Administrador' || session.role === 'Administrador'
  const filas = tree?.data ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Maestro de Productos</h1>
        <p className="text-sm text-muted-foreground">Composición de variantes en árbol</p>
      </div>

      <form method="get" className="flex items-end gap-4">
        <div className="w-96 space-y-2">
          <label className="text-sm font-medium">Variante</label>
          <select
            name="id_variante"
            defaultValue={varianteId || ''}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            <option value="">Seleccionar...</option>
            {(variantes?.data ?? []).map((v) => (
              <option key={v.id} value={v.id}>
                {v.codigo_variante} — {v.detalle}
              </option>
            ))}
          </select>
        </div>
        <button type="submit" className="h-10 rounded-md bg-primary px-4 text-sm text-primary-foreground">
          Ver composición
        </button>
      </form>

      {varianteId > 0 ? (
        <>
          {canAdmin ? (
            <MaestroForm
              initial={{ variante_padre_id: varianteId }}
              variantes={(variantes?.data ?? []).filter((v) => v.id !== varianteId)}
              ums={ums?.data ?? []}
              onSubmit={agregarComponente}
              onCancel={() => {}}
            />
          ) : null}

          <div className="rounded-md border">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b bg-muted/50">
                  <th className="p-2 text-left font-medium">Nivel</th>
                  <th className="p-2 text-left font-medium">Código</th>
                  <th className="p-2 text-left font-medium">Detalle</th>
                  <th className="p-2 text-right font-medium">Cantidad</th>
                  <th className="p-2 text-left font-medium">Unidad</th>
                  <th className="p-2 text-left font-medium">Tipo</th>
                </tr>
              </thead>
              <tbody>
                {filas.map((f) => (
                  <tr key={`${f.variante_id}-${f.nivel}-${f.bom_detalle_id ?? 'root'}`} className="border-b">
                    <td className="p-2 text-muted-foreground">{f.nivel}</td>
                    <td className="p-2" style={{ paddingLeft: `${12 + f.nivel * 20}px` }}>
                      <code className="text-sm">{f.codigo_variante}</code>
                    </td>
                    <td className="p-2">{f.variante_detalle}</td>
                    <td className="p-2 text-right font-mono">{f.cantidad}</td>
                    <td className="p-2">{f.unidad ?? '—'}</td>
                    <td className="p-2 text-muted-foreground">{f.tipo_codigo ?? '—'}</td>
                  </tr>
                ))}
                {filas.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="h-16 text-center text-muted-foreground">
                      La variante no tiene BOM activa
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </div>
        </>
      ) : null}
    </div>
  )
}
