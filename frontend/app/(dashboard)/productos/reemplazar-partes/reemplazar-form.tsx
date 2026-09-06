'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { reemplazarPartes } from './actions'

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }
export type WhereUsedRow = { bom_id: number; padre_codigo: string; padre_detalle: string; cantidad_necesaria: number; unidad_codigo: string }

export function ReemplazarForm({
  variantes = [],
  whereUsed = [],
}: {
  variantes?: VarianteOpt[]
  whereUsed?: WhereUsedRow[]
}) {
  const [origenId, setOrigenId] = useState(0)
  const [nuevaId, setNuevaId] = useState(0)
  const [bomIds, setBomIds] = useState<number[]>([])
  const [busy, setBusy] = useState(false)
  const [resultado, setResultado] = useState<{ reemplazados: number; boms_afectadas: number } | null>(null)
  const [error, setError] = useState('')

  const toggleBom = (id: number) => {
    setBomIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
  }

  const handleOrigenChange = (v: string | null) => {
    const id = Number(v ?? 0)
    setOrigenId(id)
    if (id > 0) {
      window.location.href = `/productos/reemplazar-partes?id_variante_origen=${id}`
    }
  }

  const handleEjecutar = async () => {
    setError('')
    setResultado(null)
    if (!origenId || !nuevaId) return setError('Seleccioná la pieza a reemplazar y la de reemplazo')
    if (origenId === nuevaId) return setError('No pueden ser la misma pieza')
    setBusy(true)
    const res = await reemplazarPartes({
      variante_origen_id: origenId,
      variante_nueva_id: nuevaId,
      bom_ids: bomIds.length > 0 ? bomIds : undefined,
    })
    setBusy(false)
    if (res.ok && res.data) {
      setResultado(res.data)
    } else {
      setError(res.error ?? 'Error')
    }
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Pieza a reemplazar (X)</Label>
          <Select value={String(origenId)} onValueChange={handleOrigenChange}>
            <SelectTrigger><SelectValue placeholder="Pieza X" /></SelectTrigger>
            <SelectContent>
              {variantes.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>{v.codigo_variante} — {v.detalle}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Pieza de reemplazo (H)</Label>
          <Select value={String(nuevaId)} onValueChange={(v) => setNuevaId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Pieza H" /></SelectTrigger>
            <SelectContent>
              {variantes.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>{v.codigo_variante} — {v.detalle}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {whereUsed.length > 0 ? (
        <div className="rounded-md border p-4">
          <p className="mb-2 text-sm font-medium">La pieza X aparece en {whereUsed.length} BOMs. Seleccioná dónde reemplazar:</p>
          <div className="space-y-1">
            {whereUsed.map((w) => (
              <label key={w.bom_id} className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={bomIds.includes(w.bom_id)}
                  onChange={() => toggleBom(w.bom_id)}
                  className="h-4 w-4"
                />
                {w.padre_codigo} — {w.padre_detalle} (cant. {w.cantidad_necesaria} {w.unidad_codigo})
              </label>
            ))}
          </div>
        </div>
      ) : null}

      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <Button onClick={handleEjecutar} disabled={busy}>
        {busy ? 'Reemplazando...' : 'Ejecutar reemplazo'}
      </Button>
      {resultado ? (
        <div className="rounded-md border p-4 text-sm">
          <strong>{resultado.reemplazados}</strong> componentes reemplazados en <strong>{resultado.boms_afectadas}</strong> BOMs.
        </div>
      ) : null}
    </div>
  )
}
