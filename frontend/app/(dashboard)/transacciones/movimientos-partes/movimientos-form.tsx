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
import { registrarMovimiento } from './actions'
import { useRouter } from 'next/navigation'
import { toast } from 'sonner'

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }
export type AlmacenOpt = { id: number; codigo: string; nombre: string }

const TIPOS = [
  'compra_recepcion',
  'produccion_ingreso',
  'produccion_consumo',
  'produccion_descarte',
  'ajuste_inventario',
  'venta_despacho',
  'transferencia_salida',
  'transferencia_entrada',
]

const TIPO_LABELS: Record<string, string> = {
  compra_recepcion: 'Recepción de compra',
  produccion_ingreso: 'Ingreso de producción',
  produccion_consumo: 'Consumo de producción',
  produccion_descarte: 'Descarte de producción',
  ajuste_inventario: 'Ajuste de inventario',
  venta_despacho: 'Despacho de venta',
  transferencia_salida: 'Salida por transferencia',
  transferencia_entrada: 'Entrada por transferencia',
}

export function MovimientoForm({
  variantes,
  almacenes,
}: {
  variantes: VarianteOpt[]
  almacenes: AlmacenOpt[]
}) {
  const router = useRouter()
  const [varianteId, setVarianteId] = useState(0)
  const [tipo, setTipo] = useState('ajuste_inventario')
  const [cantidad, setCantidad] = useState('1')
  const [almacenId, setAlmacenId] = useState(0)
  const [observaciones, setObservaciones] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    if (!varianteId) return setError('Seleccioná la variante')
    if (!cantidad || Number(cantidad) <= 0) return setError('Cantidad inválida')
    setBusy(true)
    const res = await registrarMovimiento({
      variante_id: varianteId,
      almacen_id: almacenId || null,
      tipo_movimiento: tipo,
      cantidad: Number(cantidad),
      observaciones: observaciones || undefined,
    })
    setBusy(false)
    if (res.ok) {
      toast.success('Movimiento registrado')
      router.refresh()
    } else {
      setError(res.error ?? 'Error')
    }
  }

  return (
    <div className="grid gap-4 rounded-md border p-4 sm:grid-cols-2 lg:grid-cols-3">
      <div className="space-y-2">
        <Label>Variante</Label>
        <Select value={String(varianteId)} onValueChange={(v) => setVarianteId(Number(v))}>
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
        <Label>Tipo de movimiento</Label>
        <Select value={tipo} onValueChange={(v) => v && setTipo(v)}>
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {TIPOS.map((t) => (
              <SelectItem key={t} value={t}>
                {TIPO_LABELS[t]}
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
        <Label>Almacén (opcional)</Label>
        <Select value={String(almacenId)} onValueChange={(v) => setAlmacenId(Number(v))}>
          <SelectTrigger>
            <SelectValue placeholder="Sin almacén" />
          </SelectTrigger>
          <SelectContent>
            {almacenes.map((a) => (
              <SelectItem key={a.id} value={String(a.id)}>
                {a.nombre} ({a.codigo})
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>Observaciones</Label>
        <Input value={observaciones} onChange={(e) => setObservaciones(e.target.value)} />
      </div>
      <div className="flex items-end">
        <Button onClick={handleSubmit} disabled={busy} className="w-full">
          {busy ? 'Registrando...' : 'Registrar movimiento'}
        </Button>
      </div>
      {error ? <p className="text-sm text-destructive sm:col-span-2 lg:col-span-3">{error}</p> : null}
    </div>
  )
}
