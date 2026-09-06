import { getSession } from '@/lib/session'
import { apiFetch } from '@/lib/api'

export const dynamic = 'force-dynamic'

type Tarea = { id: number; numero_orden: string; variante_codigo: string; prioridad: string; cantidad: number; inicio: string | null; fin: string | null; estado: string }
type Fila = { centro_id: number; centro_codigo: string; centro_nombre: string; tareas: Tarea[] }

export default async function GanttPage({ searchParams }: { searchParams: Promise<{ centro_id?: string }> }) {
  const session = await getSession()
  if (!session) return null
  const sp = await searchParams
  const centroId = sp.centro_id

  const res = await apiFetch<{ data: { periodo: { inicio: string; fin: string }; filas: Fila[] } }>(
    `/api/v1/operaciones/gantt${centroId ? `?centro_id=${centroId}` : ''}`,
    session.accessToken
  ).catch(() => null)

  const filas = res?.data.filas ?? []
  const periodo = res?.data.periodo ?? { inicio: '', fin: '' }

  // Calcular posición de cada tarea en el timeline (días desde inicio)
  const inicio = new Date(periodo.inicio).getTime()
  const fin = new Date(periodo.fin).getTime()
  const totalDias = Math.max(1, Math.ceil((fin - inicio) / 86400000))

  const posicion = (t: Tarea) => {
    if (!t.inicio || !t.fin) return { left: 0, width: 0 }
    const s = new Date(t.inicio).getTime()
    const e = new Date(t.fin).getTime()
    const left = Math.max(0, (s - inicio) / 86400000)
    const width = Math.max(1, (e - s) / 86400000)
    return { left, width }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Vista Gantt</h1>
        <p className="text-sm text-muted-foreground">Planificación por centro de trabajo</p>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <div className="min-w-[800px]">
          {/* Header de días */}
          <div className="flex border-b bg-muted/50">
            <div className="w-48 shrink-0 p-2 text-sm font-medium">Centro</div>
            <div className="relative flex-1">
              {Array.from({ length: Math.min(totalDias, 30) }).map((_, i) => (
                <div key={i} className="absolute top-0 h-full border-l text-[10px] text-muted-foreground" style={{ left: `${(i / totalDias) * 100}%` }}>
                  <span className="ml-1">{new Date(inicio + i * 86400000).getDate()}</span>
                </div>
              ))}
            </div>
          </div>
          {filas.map((f) => (
            <div key={f.centro_id} className="flex border-b">
              <div className="w-48 shrink-0 p-2 text-sm">
                <strong>{f.centro_nombre}</strong>
                <span className="block text-xs text-muted-foreground">{f.centro_codigo}</span>
              </div>
              <div className="relative h-14 flex-1">
                {f.tareas.map((t) => {
                  const { left, width } = posicion(t)
                  return (
                    <div
                      key={t.id}
                      className="absolute top-2 h-9 overflow-hidden rounded border px-1.5 text-[11px] leading-9"
                      style={{
                        left: `${(left / totalDias) * 100}%`,
                        width: `${Math.max(2, (width / totalDias) * 100)}%`,
                        backgroundColor: t.estado === 'completado' ? 'hsl(142 76% 36%)' : t.estado === 'en_ejecucion' ? 'hsl(221 83% 53%)' : 'hsl(48 96% 53%)',
                        color: '#000',
                      }}
                      title={`${t.numero_orden} — ${t.variante_codigo} (${t.cantidad})`}
                    >
                      {t.numero_orden}
                    </div>
                  )
                })}
              </div>
            </div>
          ))}
          {filas.length === 0 ? (
            <p className="p-8 text-center text-muted-foreground">Sin planificaciones en el período</p>
          ) : null}
        </div>
      </div>
    </div>
  )
}
