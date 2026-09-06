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

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }
export type UmOpt = { id: number; simbolo: string }

export function MaestroForm({
  initial,
  variantes = [],
  ums = [],
  onSubmit,
  onCancel,
}: {
  initial: { variante_padre_id: number } | null
  variantes?: VarianteOpt[]
  ums?: UmOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<{ ok?: boolean; error?: string }>
  onCancel: () => void
}) {
  const [componenteId, setComponenteId] = useState(0)
  const [cantidad, setCantidad] = useState('1')
  const [umId, setUmId] = useState(0)
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    if (!initial?.variante_padre_id) return setError('Seleccioná la variante padre')
    if (!componenteId) return setError('Seleccioná el componente')
    if (!cantidad || Number(cantidad) <= 0) return setError('Cantidad inválida')
    if (!umId) return setError('Seleccioná la unidad')
    await onSubmit({
      variante_padre_id: initial.variante_padre_id,
      variante_componente_id: componenteId,
      cantidad_necesaria: Number(cantidad),
      unidad_medida_id: umId,
    })
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Componente</Label>
          <Select value={String(componenteId)} onValueChange={(v) => setComponenteId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Componente" /></SelectTrigger>
            <SelectContent>
              {variantes.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>
                  {v.codigo_variante} — {v.detalle}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Cantidad</Label>
          <Input type="number" step="any" min={0} value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Unidad</Label>
          <Select value={String(umId)} onValueChange={(v) => setUmId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Unidad" /></SelectTrigger>
            <SelectContent>
              {ums.map((u) => (
                <SelectItem key={u.id} value={String(u.id)}>{u.simbolo}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel}>Cancelar</Button>
        <Button onClick={handleSubmit}>Agregar</Button>
      </div>
    </div>
  )
}
