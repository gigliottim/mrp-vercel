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
import { copiarComponentes } from './actions'

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }

export function CopiarForm({ variantes = [] }: { variantes?: VarianteOpt[] }) {
  const [origenId, setOrigenId] = useState(0)
  const [destinoId, setDestinoId] = useState(0)
  const [busy, setBusy] = useState(false)
  const [resultado, setResultado] = useState<{ copiados: number; eliminados: number; saltados: string[] } | null>(null)
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    setResultado(null)
    if (!origenId || !destinoId) return setError('Seleccioná origen y destino')
    if (origenId === destinoId) return setError('Origen y destino no pueden ser la misma pieza')
    setBusy(true)
    const res = await copiarComponentes({ variante_origen_id: origenId, variante_destino_id: destinoId })
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
          <Label>Pieza de origen</Label>
          <Select value={String(origenId)} onValueChange={(v) => setOrigenId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Origen" /></SelectTrigger>
            <SelectContent>
              {variantes.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>{v.codigo_variante} — {v.detalle}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>Pieza de destino</Label>
          <Select value={String(destinoId)} onValueChange={(v) => setDestinoId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Destino" /></SelectTrigger>
            <SelectContent>
              {variantes.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>{v.codigo_variante} — {v.detalle}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <Button onClick={handleSubmit} disabled={busy}>
        {busy ? 'Copiando...' : 'Copiar componentes'}
      </Button>
      {resultado ? (
        <div className="rounded-md border p-4 text-sm">
          <p><strong>{resultado.copiados}</strong> componentes copiados, <strong>{resultado.eliminados}</strong> eliminados del destino.</p>
          {resultado.saltados.length > 0 ? (
            <ul className="mt-2 list-disc pl-5 text-muted-foreground">
              {resultado.saltados.map((s, i) => <li key={i}>{s}</li>)}
            </ul>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
