'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
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
import { Label } from '@/components/ui/label'
import { clonarRuta } from './actions'
import type { BomOpt } from './ruta-form'

export function ClonarRutaDialog({
  bomId,
  boms,
}: {
  bomId: number
  boms: BomOpt[]
}) {
  const router = useRouter()
  const [open, setOpen] = useState(false)
  const [destinoId, setDestinoId] = useState(0)
  const [busy, setBusy] = useState(false)

  const handleClonar = async () => {
    if (!destinoId) return
    setBusy(true)
    const res = await clonarRuta(bomId, destinoId)
    setBusy(false)
    if (res.ok) {
      toast.success('Ruta clonada')
      setOpen(false)
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <>
      <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
        Clonar
      </Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Clonar ruta del BOM #{bomId}</DialogTitle>
          </DialogHeader>
          <div className="space-y-2">
            <Label>BOM destino (debe estar sin operaciones)</Label>
            <Select value={String(destinoId)} onValueChange={(v) => setDestinoId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Seleccionar BOM destino" />
              </SelectTrigger>
              <SelectContent>
                {boms
                  .filter((b) => b.id !== bomId)
                  .map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      #{b.id}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>
          <div className="flex justify-end gap-2">
            <Button variant="outline" onClick={() => setOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleClonar} disabled={busy || !destinoId}>
              {busy ? 'Clonando...' : 'Clonar'}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </>
  )
}