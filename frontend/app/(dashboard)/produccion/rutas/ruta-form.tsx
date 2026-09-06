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

export type RutaRow = {
  id: number
  bom_id: number
  secuencia: number
  centro_trabajo_id: number
  descripcion: string
  tiempo_setup_mins: number
  tiempo_proceso_unitario_mins: number
}

export type BomOpt = { id: number; version: string }
export type CentroOpt = { id: number; codigo: string; nombre: string }

export function RutaForm({
  initial,
  boms,
  centros,
  onSubmit,
  onCancel,
}: {
  initial: RutaRow | null
  boms: BomOpt[]
  centros: CentroOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const [bomId, setBomId] = useState(initial?.bom_id ?? 0)
  const [centroId, setCentroId] = useState(initial?.centro_trabajo_id ?? 0)
  const [secuencia, setSecuencia] = useState(initial?.secuencia?.toString() ?? '1')
  const [descripcion, setDescripcion] = useState(initial?.descripcion ?? '')
  const [setup, setSetup] = useState(initial?.tiempo_setup_mins?.toString() ?? '0')
  const [proceso, setProceso] = useState(initial?.tiempo_proceso_unitario_mins?.toString() ?? '0')
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    if (!bomId) return setError('Seleccioná la BOM')
    if (!centroId) return setError('Seleccioná el centro de trabajo')
    await onSubmit({
      bom_id: bomId,
      centro_trabajo_id: centroId,
      secuencia: Number(secuencia),
      descripcion,
      tiempo_setup_mins: Number(setup),
      tiempo_proceso_unitario_mins: Number(proceso),
    })
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>BOM</Label>
          <Select value={String(bomId)} onValueChange={(v) => setBomId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="BOM" /></SelectTrigger>
            <SelectContent>
              {boms.map((b) => (
                <SelectItem key={b.id} value={String(b.id)}>BOM #{b.id} v{b.version}</SelectItem>
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
          <Label>Secuencia</Label>
          <Input type="number" value={secuencia} onChange={(e) => setSecuencia(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Descripción</Label>
          <Input value={descripcion} onChange={(e) => setDescripcion(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Setup (min)</Label>
          <Input type="number" value={setup} onChange={(e) => setSetup(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Proceso unitario (min)</Label>
          <Input type="number" step="any" value={proceso} onChange={(e) => setProceso(e.target.value)} />
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel}>Cancelar</Button>
        <Button onClick={handleSubmit}>Guardar</Button>
      </div>
    </div>
  )
}
