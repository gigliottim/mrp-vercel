'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { crear } from './actions'

export type VarianteOpt = { id: number; codigo_variante: string; detalle: string }
export type BomOpt = { id: number; version: string; variante_padre_id: number }

export function NuevaOrdenButton({
  variantes,
  boms,
}: {
  variantes: VarianteOpt[]
  boms: BomOpt[]
}) {
  const router = useRouter()
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [varianteId, setVarianteId] = useState(0)
  const [bomId, setBomId] = useState(0)
  const [cantidad, setCantidad] = useState('1')
  const [inicio, setInicio] = useState(new Date().toISOString().slice(0, 10))
  const [fin, setFin] = useState(new Date(Date.now() + 86400000).toISOString().slice(0, 10))
  const [prioridad, setPrioridad] = useState('normal')
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    if (!varianteId) return setError('Seleccioná la variante')
    if (!cantidad || Number(cantidad) <= 0) return setError('Cantidad inválida')
    setBusy(true)
    const res = await crear({
      variante_id: varianteId,
      bom_id_utilizada: bomId || undefined,
      cantidad_planificada: Number(cantidad),
      fecha_inicio_programada: inicio,
      fecha_fin_programada: fin,
      prioridad,
    })
    setBusy(false)
    if (res.ok) {
      toast.success('Orden creada')
      setOpen(false)
      router.refresh()
    } else {
      setError(res.error ?? 'Error')
    }
  }

  return (
    <>
      <Button onClick={() => setOpen(true)}>
        <Plus className="mr-2 h-4 w-4" />
        Nueva orden
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Nueva orden de producción</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-2">
              <Label>Variante</Label>
              <Select value={String(varianteId)} onValueChange={(v) => setVarianteId(Number(v))}>
                <SelectTrigger>
                  <SelectValue placeholder="Variante a producir" />
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
              <Label>BOM (opcional)</Label>
              <Select value={String(bomId)} onValueChange={(v) => setBomId(Number(v))}>
                <SelectTrigger>
                  <SelectValue placeholder="Sin BOM" />
                </SelectTrigger>
                <SelectContent>
                  {boms.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      BOM #{b.id} v{b.version}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Cantidad planificada</Label>
                <Input type="number" step="any" min={0} value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>Prioridad</Label>
                <Select value={prioridad} onValueChange={(v) => v && setPrioridad(v)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {['baja', 'normal', 'alta', 'urgente'].map((p) => (
                      <SelectItem key={p} value={p}>
                        {p}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>Inicio programado</Label>
                <Input type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>Fin programado</Label>
                <Input type="date" value={fin} onChange={(e) => setFin(e.target.value)} />
              </div>
            </div>
            {error ? <p className="text-sm text-destructive">{error}</p> : null}
            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={() => setOpen(false)}>
                Cancelar
              </Button>
              <Button onClick={handleSubmit} disabled={busy}>
                {busy ? 'Creando...' : 'Crear'}
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}
