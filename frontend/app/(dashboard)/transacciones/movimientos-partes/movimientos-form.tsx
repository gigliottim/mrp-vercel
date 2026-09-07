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
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { registrarMovimientoPartes } from './actions'
import { useRouter } from 'next/navigation'
import { toast } from 'sonner'

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }
export type AlmacenOpt = { id: number; codigo: string; nombre: string }
export type DestinoOpt = { id: number; codigo: string; nombre: string }
export type DestinosMap = Array<{
  origen_id: number
  origen_codigo: string
  origen_nombre: string
  destinos: DestinoOpt[]
}>
export type EntidadOpt = { id: number; razon_social: string; tipo: string }

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

function sugerirTipo(origenCodigo: string | undefined, destinoCodigo: string | undefined): string {
  if (origenCodigo === 'PROVEEDOR') return 'compra_recepcion'
  if (destinoCodigo === 'CLIENTE') return 'venta_despacho'
  if (origenCodigo === 'AJUSTE') return 'ajuste_inventario'
  return 'transferencia_salida'
}

export function MovimientoForm({
  variantes,
  destinos,
  entidades,
}: {
  variantes: VarianteOpt[]
  destinos: DestinosMap
  entidades: EntidadOpt[]
}) {
  const router = useRouter()
  const [varianteId, setVarianteId] = useState(0)
  const [origenId, setOrigenId] = useState(0)
  const [destinoId, setDestinoId] = useState(0)
  const [tipo, setTipo] = useState('transferencia_salida')
  const [cantidad, setCantidad] = useState('1')
  const [entidadId, setEntidadId] = useState(0)
  const [importeTotal, setImporteTotal] = useState('')
  const [fechaHora, setFechaHora] = useState('')
  const [nroComprobante, setNroComprobante] = useState('')
  const [observaciones, setObservaciones] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const origenActual = destinos.find((d) => d.origen_id === origenId)
  const destinosDisponibles = origenActual?.destinos ?? []
  const destinoActual = destinosDisponibles.find((d) => d.id === destinoId)
  const esOrigenProveedor = origenActual?.origen_codigo === 'PROVEEDOR'
  const entidadesFiltradas = entidades.filter(
    (e) => e.tipo === 'PROVEEDOR' || e.tipo === 'AMBOS'
  )

  const handleOrigenChange = (id: number) => {
    setOrigenId(id)
    setDestinoId(0)
    setEntidadId(0)
    const origen = destinos.find((d) => d.origen_id === id)
    setTipo(sugerirTipo(origen?.origen_codigo, undefined))
  }

  const handleDestinoChange = (id: number) => {
    setDestinoId(id)
    const destino = destinosDisponibles.find((d) => d.id === id)
    setTipo(sugerirTipo(origenActual?.origen_codigo, destino?.codigo))
  }

  const [confirmar, setConfirmar] = useState(false)

  const validar = (): string | null => {
    if (!varianteId) return 'Seleccioná la variante'
    if (!origenId) return 'Seleccioná el depósito origen'
    if (!destinoId) return 'Seleccioná el depósito destino'
    if (!cantidad || Number(cantidad) <= 0) return 'Cantidad inválida'
    if (esOrigenProveedor) {
      if (!entidadId) return 'Seleccioná la entidad (proveedor)'
      if (!importeTotal || Number(importeTotal) <= 0) return 'Importe total inválido'
    }
    return null
  }

  const pedirConfirmacion = () => {
    setError('')
    const err = validar()
    if (err) return setError(err)
    setConfirmar(true)
  }

  const handleSubmit = async () => {
    setConfirmar(false)
    setBusy(true)
    const res = await registrarMovimientoPartes({
      variante_id: varianteId,
      cantidad: Number(cantidad),
      tipo_deposito_origen_id: origenId,
      tipo_deposito_destino_id: destinoId,
      tipo_movimiento: tipo,
      entidad_id: esOrigenProveedor ? entidadId : null,
      importe_total: esOrigenProveedor && importeTotal ? Number(importeTotal) : null,
      fecha_hora: fechaHora ? new Date(fechaHora).toISOString() : null,
      nro_comprobante: esOrigenProveedor && nroComprobante ? nroComprobante : null,
      observaciones: observaciones || null,
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
        <Label>Depósito origen</Label>
        <Select value={String(origenId)} onValueChange={(v) => handleOrigenChange(Number(v))}>
          <SelectTrigger>
            <SelectValue placeholder="Origen" />
          </SelectTrigger>
          <SelectContent>
            {destinos.map((d) => (
              <SelectItem key={d.origen_id} value={String(d.origen_id)}>
                {d.origen_nombre} ({d.origen_codigo})
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>Depósito destino</Label>
        <Select
          value={String(destinoId)}
          onValueChange={(v) => handleDestinoChange(Number(v))}
          disabled={!origenId}
        >
          <SelectTrigger>
            <SelectValue placeholder={origenId ? 'Destino' : 'Elegí origen primero'} />
          </SelectTrigger>
          <SelectContent>
            {destinosDisponibles.map((d) => (
              <SelectItem key={d.id} value={String(d.id)}>
                {d.nombre} ({d.codigo})
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
        <Label>Fecha y hora (opcional)</Label>
        <Input type="datetime-local" value={fechaHora} onChange={(e) => setFechaHora(e.target.value)} />
      </div>
      {esOrigenProveedor ? (
        <>
          <div className="space-y-2">
            <Label>Entidad (proveedor)</Label>
            <Select value={String(entidadId)} onValueChange={(v) => setEntidadId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Entidad" />
              </SelectTrigger>
              <SelectContent>
                {entidadesFiltradas.map((e) => (
                  <SelectItem key={e.id} value={String(e.id)}>
                    {e.razon_social}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>Importe total</Label>
            <Input
              type="number"
              step="0.01"
              min={0}
              value={importeTotal}
              onChange={(e) => setImporteTotal(e.target.value)}
            />
          </div>
          <div className="space-y-2">
            <Label>Nro. comprobante (opcional)</Label>
            <Input value={nroComprobante} onChange={(e) => setNroComprobante(e.target.value)} />
          </div>
        </>
      ) : null}
      <div className="space-y-2">
        <Label>Observaciones</Label>
        <Input value={observaciones} onChange={(e) => setObservaciones(e.target.value)} />
      </div>
      <div className="flex items-end">
        <Button onClick={pedirConfirmacion} disabled={busy} className="w-full">
          {busy ? 'Registrando...' : 'Registrar movimiento'}
        </Button>
        <AlertDialog open={confirmar} onOpenChange={setConfirmar}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>¿Registrar movimiento?</AlertDialogTitle>
              <AlertDialogDescription>
                {tipo} de {cantidad} u. desde {origenActual?.origen_nombre ?? 'el origen'} hacia{' '}
                {destinoActual?.nombre ?? 'el destino'}.
                Esta acción ajusta el stock y no se puede deshacer.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancelar</AlertDialogCancel>
              <AlertDialogAction onClick={handleSubmit}>Registrar</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
      {error ? <p className="text-sm text-destructive sm:col-span-2 lg:col-span-3">{error}</p> : null}
    </div>
  )
}
