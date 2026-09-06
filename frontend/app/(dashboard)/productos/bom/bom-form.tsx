'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Plus, Trash2 } from 'lucide-react'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

export type BomRow = {
  id: number
  variante_padre_id: number
  version: string
  activa: boolean
  fecha_efectiva: string
  fecha_vencimiento: string | null
  observaciones: string | null
}

export type BomDetalle = {
  variante_componente_id: number
  cantidad_necesaria: number
  unidad_medida_id: number
  desperdicio_porcentaje?: number
  es_opcional?: boolean
  secuencia: number
  observaciones?: string
}

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }
export type UmOpt = { id: number; unidad: string; simbolo: string }

export function BomForm({
  initial,
  variantes = [],
  ums = [],
  onSubmit,
  onCancel,
}: {
  initial: BomRow | null
  variantes?: VarianteOpt[]
  ums?: UmOpt[]
  onSubmit: (data: Record<string, unknown>) => Promise<void>
  onCancel: () => void
}) {
  const [variantePadre, setVariantePadre] = useState<number>(initial?.variante_padre_id ?? 0)
  const [version, setVersion] = useState(initial?.version ?? '1.0')
  const [fechaEfectiva, setFechaEfectiva] = useState(initial?.fecha_efectiva ?? new Date().toISOString().slice(0, 10))
  const [detalles, setDetalles] = useState<BomDetalle[]>([])
  const [error, setError] = useState('')

  const addDetalle = () => {
    setDetalles((prev) => [
      ...prev,
      {
        variante_componente_id: 0,
        cantidad_necesaria: 1,
        unidad_medida_id: 0,
        secuencia: prev.length + 1,
      },
    ])
  }

  const updateDetalle = (idx: number, patch: Partial<BomDetalle>) => {
    setDetalles((prev) => prev.map((d, i) => (i === idx ? { ...d, ...patch } : d)))
  }

  const removeDetalle = (idx: number) => {
    setDetalles((prev) => prev.filter((_, i) => i !== idx))
  }

  const handleSubmit = async () => {
    setError('')
    if (!variantePadre) return setError('Seleccioná la variante padre')
    if (detalles.length === 0) return setError('Agregá al menos un componente')
    const invalid = detalles.find((d) => !d.variante_componente_id || !d.unidad_medida_id || d.cantidad_necesaria <= 0)
    if (invalid) return setError('Completá todos los componentes (variante, cantidad y unidad)')
    await onSubmit({
      variante_padre_id: variantePadre,
      version,
      fecha_efectiva: fechaEfectiva,
      detalles: detalles.map((d) => ({ ...d, secuencia: d.secuencia })),
    })
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-3 gap-4">
        <div className="space-y-2">
          <Label>Variante padre</Label>
          <Select value={String(variantePadre)} onValueChange={(v) => setVariantePadre(Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder="Variante" />
            </SelectTrigger>
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
          <Label>Versión</Label>
          <Input value={version} onChange={(e) => setVersion(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Fecha efectiva</Label>
          <Input type="date" value={fechaEfectiva} onChange={(e) => setFechaEfectiva(e.target.value)} />
        </div>
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <Label>Componentes</Label>
          <Button type="button" variant="outline" size="sm" onClick={addDetalle}>
            <Plus className="mr-1 h-3 w-3" /> Componente
          </Button>
        </div>
        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Variante</TableHead>
                <TableHead className="w-24">Cantidad</TableHead>
                <TableHead>Unidad</TableHead>
                <TableHead className="w-24">% desperdicio</TableHead>
                <TableHead className="w-10" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {detalles.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={5} className="h-16 text-center text-muted-foreground">
                    Sin componentes
                  </TableCell>
                </TableRow>
              ) : (
                detalles.map((d, i) => (
                  <TableRow key={i}>
                    <TableCell>
                      <Select
                        value={String(d.variante_componente_id)}
                        onValueChange={(v) => updateDetalle(i, { variante_componente_id: Number(v) })}
                      >
                        <SelectTrigger className="h-8">
                          <SelectValue placeholder="Componente" />
                        </SelectTrigger>
                        <SelectContent>
                          {variantes.map((v) => (
                            <SelectItem key={v.id} value={String(v.id)}>
                              {v.codigo_variante} — {v.detalle}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </TableCell>
                    <TableCell>
                      <Input
                        type="number"
                        step="any"
                        min={0}
                        className="h-8"
                        value={d.cantidad_necesaria}
                        onChange={(e) => updateDetalle(i, { cantidad_necesaria: Number(e.target.value) })}
                      />
                    </TableCell>
                    <TableCell>
                      <Select
                        value={String(d.unidad_medida_id)}
                        onValueChange={(v) => updateDetalle(i, { unidad_medida_id: Number(v) })}
                      >
                        <SelectTrigger className="h-8">
                          <SelectValue placeholder="Unidad" />
                        </SelectTrigger>
                        <SelectContent>
                          {ums.map((u) => (
                            <SelectItem key={u.id} value={String(u.id)}>
                              {u.unidad} ({u.simbolo})
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </TableCell>
                    <TableCell>
                      <Input
                        type="number"
                        step="any"
                        min={0}
                        className="h-8"
                        value={d.desperdicio_porcentaje ?? 0}
                        onChange={(e) => updateDetalle(i, { desperdicio_porcentaje: Number(e.target.value) })}
                      />
                    </TableCell>
                    <TableCell>
                      <Button type="button" variant="ghost" size="icon" onClick={() => removeDetalle(i)}>
                        <Trash2 className="h-4 w-4 text-destructive" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {error ? <p className="text-sm text-destructive">{error}</p> : null}

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancelar
        </Button>
        <Button type="button" onClick={handleSubmit}>
          Guardar
        </Button>
      </div>
    </div>
  )
}
