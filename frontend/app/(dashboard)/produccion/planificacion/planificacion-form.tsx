'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

export type PlanRow = {
  id: number
  orden_produccion_id: number
  centro_trabajo_id: number
  periodo: string
  estado: string
}

export type OrdenOpt = { id: number; numero_orden: string }
export type CentroOpt = { id: number; codigo: string; nombre: string }

export function PlanificacionForm({
  initial,
  ordenes = [],
  centros = [],
  onSubmit,
  onCancel,
}: {
  initial: PlanRow | null
  ordenes?: OrdenOpt[]
  centros?: CentroOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const [ordenId, setOrdenId] = useState(initial?.orden_produccion_id ?? 0)
  const [centroId, setCentroId] = useState(initial?.centro_trabajo_id ?? 0)
  const [inicio, setInicio] = useState(new Date().toISOString().slice(0, 16))
  const [fin, setFin] = useState(new Date(Date.now() + 3600000).toISOString().slice(0, 16))
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    if (!ordenId) return setError('Seleccioná la orden')
    if (!centroId) return setError('Seleccioná el centro de trabajo')
    const inicioISO = new Date(inicio).toISOString()
    const finISO = new Date(fin).toISOString()
    if (new Date(inicioISO) >= new Date(finISO)) return setError('El inicio debe ser anterior al fin')
    await onSubmit({
      orden_produccion_id: ordenId,
      centro_trabajo_id: centroId,
      inicio: inicioISO,
      fin: finISO,
    })
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Orden de producción</Label>
          <Select value={String(ordenId)} onValueChange={(v) => setOrdenId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Orden" /></SelectTrigger>
            <SelectContent>
              {ordenes.map((o) => (
                <SelectItem key={o.id} value={String(o.id)}>{o.numero_orden}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Centro de trabajo</Label>
          <Select value={String(centroId)} onValueChange={(v) => setCentroId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Centro" /></SelectTrigger>
            <SelectContent>
              {centros.map((c) => (
                <SelectItem key={c.id} value={String(c.id)}>{c.nombre} ({c.codigo})</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Inicio</Label>
          <Input type="datetime-local" value={inicio} onChange={(e) => setInicio(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Fin</Label>
          <Input type="datetime-local" value={fin} onChange={(e) => setFin(e.target.value)} />
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel}>Cancelar</Button>
        <Button onClick={handleSubmit}>Programar</Button>
      </div>
    </div>
  )
}
