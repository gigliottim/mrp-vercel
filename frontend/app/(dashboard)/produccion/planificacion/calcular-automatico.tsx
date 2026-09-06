'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { toast } from 'sonner'

export type OrdenOpt = { id: number; numero_orden: string }

export function CalcularAutomatico({ token, ordenes = [] }: { token: string; ordenes?: OrdenOpt[] }) {
  const [ordenId, setOrdenId] = useState(0)
  const [fechaInicio, setFechaInicio] = useState(new Date().toISOString().slice(0, 16))
  const [busy, setBusy] = useState(false)
  const [resultado, setResultado] = useState<Array<{ secuencia: number; fecha_inicio: string; fecha_fin: string; duracion_mins: number }> | null>(null)
  const [error, setError] = useState('')

  const handleCalcular = async () => {
    setError('')
    setResultado(null)
    if (!ordenId) return setError('Seleccioná una orden')
    setBusy(true)
    try {
      const res = await fetch(`${process.env.NEXT_PUBLIC_API_URL ?? 'https://api.mimrp.com.ar'}/api/v1/planificacion/calcular`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ orden_id: ordenId, fecha_inicio: new Date(fechaInicio).toISOString() }),
      })
      const body = await res.json()
      if (!res.ok) {
        setError(body.error?.message ?? 'Error')
      } else {
        setResultado(body.data.planificaciones)
        toast.success(`${body.data.planificaciones.length} operaciones planificadas`)
        window.location.reload()
      }
    } catch (e) {
      setError((e as Error).message)
    }
    setBusy(false)
  }

  return (
    <div className="rounded-md border p-4">
      <h2 className="mb-3 font-semibold">Planificación automática desde la ruta</h2>
      <div className="flex flex-wrap items-end gap-3">
        <div className="w-64 space-y-1.5">
          <Label>Orden de producción</Label>
          <select
            value={ordenId}
            onChange={(e) => setOrdenId(Number(e.target.value))}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            <option value={0}>Seleccionar...</option>
            {ordenes.map((o) => (
              <option key={o.id} value={o.id}>{o.numero_orden}</option>
            ))}
          </select>
        </div>
        <div className="w-56 space-y-1.5">
          <Label>Fecha de inicio</Label>
          <Input type="datetime-local" value={fechaInicio} onChange={(e) => setFechaInicio(e.target.value)} />
        </div>
        <Button onClick={handleCalcular} disabled={busy}>
          {busy ? 'Calculando...' : 'Calcular y programar'}
        </Button>
      </div>
      {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
      {resultado ? (
        <div className="mt-3 rounded-md border bg-muted/30 p-3 text-sm">
          <p className="mb-1 font-medium">Operaciones programadas:</p>
          {resultado.map((r) => (
            <p key={r.secuencia} className="font-mono text-xs">
              Sec. {r.secuencia}: {new Date(r.fecha_inicio).toLocaleString('es-AR')} → {new Date(r.fecha_fin).toLocaleString('es-AR')} ({r.duracion_mins} min)
            </p>
          ))}
        </div>
      ) : null}
    </div>
  )
}
