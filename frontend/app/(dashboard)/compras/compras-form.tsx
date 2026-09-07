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

export type CompraRow = {
  id: number
  fecha: string
  precio_unitario: number
  id_entidad: number
  nro_comprobante: string | null
  observaciones: string | null
}

export type EntidadOpt = { id: number; razon_social: string; tipo: string }
export type VarianteOpt = { id: number; codigo_variante: string }

export function CompraForm({
  initial,
  entidades = [],
  variantes = [],
  onSubmit,
  onCancel,
}: {
  initial: CompraRow | null
  entidades?: EntidadOpt[]
  variantes?: VarianteOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const [fecha, setFecha] = useState(initial?.fecha ?? new Date().toISOString().slice(0, 10))
  const [entidadId, setEntidadId] = useState(initial?.id_entidad ?? 0)
  const [varianteId, setVarianteId] = useState(0)
  const [cantidad, setCantidad] = useState('1')
  const [precio, setPrecio] = useState(initial?.precio_unitario?.toString() ?? '')
  const [comprobante, setComprobante] = useState(initial?.nro_comprobante ?? '')
  const [error, setError] = useState('')

  // Modo edición: solo fecha/proveedor/precio/comprobante (la recepción de
  // stock asociada a variante/cantidad no se re-ejecuta)
  const esEdicion = Boolean(initial?.id)

  const handleSubmit = async () => {
    setError('')
    if (!entidadId) return setError('Seleccioná el proveedor')
    if (!esEdicion && !varianteId) return setError('Seleccioná la variante')
    if (!precio || Number(precio) <= 0) return setError('Precio inválido')
    if (esEdicion) {
      await onSubmit({
        fecha,
        precio_unitario: Number(precio),
        id_entidad: entidadId,
        nro_comprobante: comprobante || undefined,
      })
      return
    }
    if (!cantidad || Number(cantidad) <= 0) return setError('Cantidad inválida')
    await onSubmit({
      fecha,
      precio_unitario: Number(precio),
      id_entidad: entidadId,
      nro_comprobante: comprobante || undefined,
      variante_id: varianteId,
      cantidad: Number(cantidad),
    })
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Fecha</Label>
          <Input type="date" value={fecha} onChange={(e) => setFecha(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Proveedor</Label>
          <Select value={String(entidadId)} onValueChange={(v) => setEntidadId(Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder="Proveedor" />
            </SelectTrigger>
            <SelectContent>
              {entidades.map((e) => (
                <SelectItem key={e.id} value={String(e.id)}>
                  {e.razon_social}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      {!esEdicion ? (
        <>
          <div className="space-y-2">
            <Label>Variante</Label>
            <Select value={String(varianteId)} onValueChange={(v) => setVarianteId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Variante" />
              </SelectTrigger>
              <SelectContent>
                {variantes.map((v) => (
                  <SelectItem key={v.id} value={String(v.id)}>
                    {v.codigo_variante}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>Cantidad</Label>
            <Input type="number" step="any" min={0} value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
          </div>
        </>
      ) : null}
        <div className="space-y-2">
          <Label>Precio unitario</Label>
          <Input type="number" step="any" min={0} value={precio} onChange={(e) => setPrecio(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Nº comprobante</Label>
          <Input value={comprobante} onChange={(e) => setComprobante(e.target.value)} />
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button onClick={handleSubmit}>{esEdicion ? 'Actualizar' : 'Comprar y recibir'}</Button>
      </div>
    </div>
  )
}
