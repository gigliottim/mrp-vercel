'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { toast } from 'sonner'
import { agregarOperacion, actualizarOperacion, eliminarOperacion, reordenarOperaciones } from './actions'

export type Operacion = {
  id: number
  secuencia: number
  centro_trabajo_id: number
  descripcion: string
  tiempo_setup_mins: number | null
  tiempo_proceso_unitario_mins: number | null
  tiempo_cola_mins: number | null
  tiempo_movimiento_mins: number | null
  capacidad_requerida: number | null
  costo_operacion_fijo: number | null
  costo_operacion_variable: number | null
  instrucciones: string | null
  centros_trabajo?: { codigo: string; nombre: string; activo: boolean }
}

export type Centro = { id: number; codigo: string; nombre: string; activo: boolean }
export type Resumen = {
  tiempos: { total_mins: number; total_horas: number; por_operacion: Array<{ secuencia: number; descripcion: string; centro: string; duracion_mins: number; duracion_horas: number }> }
  costos: { total: number; por_operacion: Array<{ secuencia: number; descripcion: string; costo_fijo: number; costo_variable: number; costo_total: number }> }
  validaciones: string[]
  es_valida: boolean
}

export function EditorClient({
  bomId,
  operaciones,
  centros,
  resumen,
  canAdmin,
}: {
  bomId: number
  operaciones: Operacion[]
  centros: Centro[]
  resumen: Resumen | null
  canAdmin: boolean
}) {
  const [ops, setOps] = useState(operaciones)
  const [secuencia, setSecuencia] = useState(String(ops.length + 1))
  const [centroId, setCentroId] = useState(0)
  const [descripcion, setDescripcion] = useState('')
  const [setup, setSetup] = useState('0')
  const [proceso, setProceso] = useState('0')
  const [cola, setCola] = useState('0')
  const [movimiento, setMovimiento] = useState('0')
  const [costoFijo, setCostoFijo] = useState('0')
  const [costoVar, setCostoVar] = useState('0')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const handleAgregar = async () => {
    setError('')
    if (!centroId) return setError('Seleccioná el centro de trabajo')
    if (!descripcion.trim()) return setError('Descripción requerida')
    setBusy(true)
    const res = await agregarOperacion(bomId, {
      secuencia: Number(secuencia),
      centro_trabajo_id: centroId,
      descripcion: descripcion.trim(),
      tiempo_setup_mins: Number(setup) || 0,
      tiempo_proceso_unitario_mins: Number(proceso) || 0,
      tiempo_cola_mins: Number(cola) || 0,
      tiempo_movimiento_mins: Number(movimiento) || 0,
      costo_operacion_fijo: Number(costoFijo) || 0,
      costo_operacion_variable: Number(costoVar) || 0,
    })
    setBusy(false)
    if (res.ok) {
      toast.success('Operación agregada')
      window.location.reload()
    } else {
      setError(res.error ?? 'Error')
    }
  }

  const handleEliminar = async (op: Operacion) => {
    if (!confirm(`¿Eliminar la operación ${op.secuencia}?`)) return
    const res = await eliminarOperacion(op.id, bomId)
    if (res.ok) {
      toast.success('Operación eliminada')
      setOps((prev) => prev.filter((x) => x.id !== op.id))
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  const handleMover = async (index: number, direccion: -1 | 1) => {
    const destino = index + direccion
    if (destino < 0 || destino >= ops.length) return
    const nuevo = [...ops]
    const [movida] = nuevo.splice(index, 1)
    nuevo.splice(destino, 0, movida)
    setOps(nuevo)
    const res = await reordenarOperaciones(bomId, nuevo.map((o) => o.id))
    if (res.ok) {
      toast.success('Orden actualizado')
    } else {
      toast.error(res.error ?? 'Error')
      setOps(operaciones)
    }
  }

  return (
    <div className="space-y-6">
      {canAdmin ? (
        <div className="rounded-md border p-4">
          <h2 className="mb-3 font-semibold">Nueva operación</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1.5">
              <Label>Secuencia</Label>
              <Input type="number" value={secuencia} onChange={(e) => setSecuencia(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Centro de trabajo</Label>
              <select
                value={centroId}
                onChange={(e) => setCentroId(Number(e.target.value))}
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              >
                <option value={0}>Seleccionar...</option>
                {centros.map((c) => (
                  <option key={c.id} value={c.id}>{c.nombre} ({c.codigo})</option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5 sm:col-span-2">
              <Label>Descripción</Label>
              <Input value={descripcion} onChange={(e) => setDescripcion(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Setup (min)</Label>
              <Input type="number" value={setup} onChange={(e) => setSetup(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Proceso unitario (min)</Label>
              <Input type="number" step="any" value={proceso} onChange={(e) => setProceso(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Cola (min)</Label>
              <Input type="number" value={cola} onChange={(e) => setCola(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Movimiento (min)</Label>
              <Input type="number" value={movimiento} onChange={(e) => setMovimiento(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Costo fijo</Label>
              <Input type="number" step="any" value={costoFijo} onChange={(e) => setCostoFijo(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Costo variable/ud</Label>
              <Input type="number" step="any" value={costoVar} onChange={(e) => setCostoVar(e.target.value)} />
            </div>
          </div>
          {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
          <Button className="mt-3" onClick={handleAgregar} disabled={busy}>
            {busy ? 'Agregando...' : 'Agregar operación'}
          </Button>
        </div>
      ) : null}

      <div className="rounded-md border">
        <h2 className="border-b p-4 font-semibold">Operaciones ({ops.length})</h2>
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Sec.</th>
              <th className="p-2 font-medium">Centro</th>
              <th className="p-2 font-medium">Descripción</th>
              <th className="p-2 text-right font-medium">Setup</th>
              <th className="p-2 text-right font-medium">Proceso</th>
              <th className="p-2 text-right font-medium">Cola</th>
              <th className="p-2 text-right font-medium">Mov.</th>
              <th className="p-2 text-right font-medium">Costo fijo</th>
              <th className="p-2 text-right font-medium">Costo var.</th>
              {canAdmin ? <th className="p-2 font-medium">Acciones</th> : null}
            </tr>
          </thead>
          <tbody>
            {ops.map((op, index) => (
              <tr key={op.id} className="border-b">
                <td className="p-2">
                  <span>{op.secuencia}</span>
                  {canAdmin && ops.length > 1 ? (
                    <span className="ml-1 inline-flex flex-col">
                      <button
                        aria-label="Subir"
                        disabled={index === 0}
                        onClick={() => handleMover(index, -1)}
                        className="text-xs text-muted-foreground hover:text-foreground disabled:opacity-30"
                      >
                        ▲
                      </button>
                      <button
                        aria-label="Bajar"
                        disabled={index === ops.length - 1}
                        onClick={() => handleMover(index, 1)}
                        className="text-xs text-muted-foreground hover:text-foreground disabled:opacity-30"
                      >
                        ▼
                      </button>
                    </span>
                  ) : null}
                </td>
                <td className="p-2">
                  {op.centros_trabajo?.nombre ?? `#${op.centro_trabajo_id}`}
                  {op.centros_trabajo && !op.centros_trabajo.activo ? <span className="ml-1 text-xs text-destructive">(inactivo)</span> : null}
                </td>
                <td className="p-2">{op.descripcion}</td>
                <td className="p-2 text-right font-mono">{op.tiempo_setup_mins ?? 0}</td>
                <td className="p-2 text-right font-mono">{op.tiempo_proceso_unitario_mins ?? 0}</td>
                <td className="p-2 text-right font-mono">{op.tiempo_cola_mins ?? 0}</td>
                <td className="p-2 text-right font-mono">{op.tiempo_movimiento_mins ?? 0}</td>
                <td className="p-2 text-right font-mono">{op.costo_operacion_fijo ?? 0}</td>
                <td className="p-2 text-right font-mono">{op.costo_operacion_variable ?? 0}</td>
                {canAdmin ? (
                  <td className="p-2">
                    <button onClick={() => handleEliminar(op)} className="text-sm text-destructive hover:underline">
                      Eliminar
                    </button>
                  </td>
                ) : null}
              </tr>
            ))}
            {ops.length === 0 ? (
              <tr>
                <td colSpan={10} className="h-16 text-center text-muted-foreground">
                  La ruta no tiene operaciones. Agregá la primera.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
      </div>

      {resumen ? (
        <div className="grid gap-4 lg:grid-cols-2">
          <div className="rounded-md border p-4">
            <h2 className="mb-2 font-semibold">Tiempos (por unidad)</h2>
            <p className="text-2xl font-bold">{resumen.tiempos.total_mins} min <span className="text-sm font-normal text-muted-foreground">({resumen.tiempos.total_horas} hs)</span></p>
            <table className="mt-2 w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="p-1.5">Sec.</th>
                  <th className="p-1.5">Operación</th>
                  <th className="p-1.5 text-right">Duración</th>
                </tr>
              </thead>
              <tbody>
                {resumen.tiempos.por_operacion.map((t) => (
                  <tr key={t.secuencia} className="border-b">
                    <td className="p-1.5">{t.secuencia}</td>
                    <td className="p-1.5">{t.descripcion} <span className="text-xs text-muted-foreground">({t.centro})</span></td>
                    <td className="p-1.5 text-right font-mono">{t.duracion_mins} min</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="rounded-md border p-4">
            <h2 className="mb-2 font-semibold">Costos (por unidad)</h2>
            <p className="text-2xl font-bold">${resumen.costos.total.toFixed(2)}</p>
            <table className="mt-2 w-full text-sm">
              <thead>
                <tr className="border-b text-left text-muted-foreground">
                  <th className="p-1.5">Sec.</th>
                  <th className="p-1.5">Operación</th>
                  <th className="p-1.5 text-right">Fijo</th>
                  <th className="p-1.5 text-right">Variable</th>
                  <th className="p-1.5 text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                {resumen.costos.por_operacion.map((c) => (
                  <tr key={c.secuencia} className="border-b">
                    <td className="p-1.5">{c.secuencia}</td>
                    <td className="p-1.5">{c.descripcion}</td>
                    <td className="p-1.5 text-right font-mono">{c.costo_fijo.toFixed(2)}</td>
                    <td className="p-1.5 text-right font-mono">{c.costo_variable.toFixed(2)}</td>
                    <td className="p-1.5 text-right font-mono">{c.costo_total.toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}

      {resumen && resumen.validaciones.length > 0 ? (
        <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800">
          <p className="font-semibold">Validaciones:</p>
          <ul className="mt-1 list-disc pl-5">
            {resumen.validaciones.map((v, i) => <li key={i}>{v}</li>)}
          </ul>
        </div>
      ) : null}
    </div>
  )
}
