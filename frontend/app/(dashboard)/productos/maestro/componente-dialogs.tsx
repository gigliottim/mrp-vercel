'use client'

import { useState } from 'react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { VarianteOpt, UmOpt, TreeRow } from './maestro-client'
import { agregarComponente, editarComponente, eliminarComponente, reemplazarComponente } from './actions'

function useSubmit(cerrar: () => void) {
  const [loading, setLoading] = useState(false)
  const submit = async (fn: () => Promise<{ ok?: boolean; error?: string }>) => {
    setLoading(true)
    const res = await fn()
    setLoading(false)
    if (res.error) {
      toast.error(res.error)
      return
    }
    toast.success('Operación completada')
    cerrar()
  }
  return { loading, submit }
}

export function AgregarComponenteDialog({
  open,
  onOpenChange,
  nodo,
  variantes,
  ums,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  nodo: TreeRow | null
  variantes: VarianteOpt[]
  ums: UmOpt[]
}) {
  const [componenteId, setComponenteId] = useState(0)
  const [cantidad, setCantidad] = useState('1')
  const [umId, setUmId] = useState(0)
  const { loading, submit } = useSubmit(() => onOpenChange(false))

  if (!nodo) return null
  const candidatos = variantes.filter((v) => v.id !== nodo.variante_id)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Agregar componente a {nodo.codigo_variante}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Componente</Label>
            <Select value={String(componenteId)} onValueChange={(v) => setComponenteId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Seleccionar componente" />
              </SelectTrigger>
              <SelectContent>
                {candidatos.map((v) => (
                  <SelectItem key={v.id} value={String(v.id)}>
                    {v.parte_codigo ? `${v.parte_codigo} / ` : ''}
                    {v.codigo_variante} — {v.detalle}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>Cantidad</Label>
              <Input type="number" step="any" min="0" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>Unidad</Label>
              <Select value={String(umId)} onValueChange={(v) => setUmId(Number(v))}>
                <SelectTrigger>
                  <SelectValue placeholder="Unidad" />
                </SelectTrigger>
                <SelectContent>
                  {ums.map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.simbolo}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            disabled={loading}
            onClick={() =>
              submit(() =>
                agregarComponente({
                  variante_padre_id: nodo.variante_id,
                  variante_componente_id: componenteId,
                  cantidad: Number(cantidad),
                  unidad_medida_id: umId,
                })
              )
            }
          >
            Agregar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function EditarComponenteDialog({
  open,
  onOpenChange,
  hijo,
  ums,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  hijo: TreeRow | null
  ums: UmOpt[]
}) {
  const [cantidad, setCantidad] = useState(String(hijo?.cantidad ?? ''))
  const [umId, setUmId] = useState(hijo?.unidad_medida_id ?? 0)
  const { loading, submit } = useSubmit(() => onOpenChange(false))

  if (!hijo?.bom_detalle_id) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Editar componente {hijo.codigo_variante}</DialogTitle>
        </DialogHeader>
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-2">
            <Label>Cantidad</Label>
            <Input type="number" step="any" min="0" value={cantidad} onChange={(e) => setCantidad(e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label>Unidad</Label>
            <Select value={String(umId)} onValueChange={(v) => setUmId(Number(v))}>
              <SelectTrigger>
                <SelectValue placeholder="Unidad" />
              </SelectTrigger>
              <SelectContent>
                {ums.map((u) => (
                  <SelectItem key={u.id} value={String(u.id)}>
                    {u.simbolo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            disabled={loading}
            onClick={() =>
              submit(() =>
                editarComponente(hijo.bom_detalle_id!, {
                  cantidad: Number(cantidad),
                  unidad_medida_id: umId,
                })
              )
            }
          >
            Guardar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function ReemplazarComponenteDialog({
  open,
  onOpenChange,
  hijo,
  nodo,
  variantes,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  hijo: TreeRow | null
  nodo: TreeRow | null
  variantes: VarianteOpt[]
}) {
  const [nuevaId, setNuevaId] = useState(0)
  const { loading, submit } = useSubmit(() => onOpenChange(false))

  if (!hijo || !nodo) return null
  const candidatas = variantes.filter((v) => v.id !== hijo.variante_id)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Reemplazar {hijo.codigo_variante}</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">
          La variante seleccionada será reemplazada en todos los componentes de esta BOM donde aparezca.
        </p>
        <div className="space-y-2">
          <Label>Variante nueva</Label>
          <Select value={String(nuevaId)} onValueChange={(v) => setNuevaId(Number(v))}>
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar variante nueva" />
            </SelectTrigger>
            <SelectContent>
              {candidatas.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>
                  {v.parte_codigo ? `${v.parte_codigo} / ` : ''}
                  {v.codigo_variante} — {v.detalle}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button
            disabled={loading || !nuevaId}
            onClick={() =>
              submit(() =>
                reemplazarComponente({
                  variante_origen_id: hijo.variante_id,
                  variante_nueva_id: nuevaId,
                  variante_padre_id: nodo.variante_id,
                })
              )
            }
          >
            Reemplazar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export function EliminarComponenteDialog({
  open,
  onOpenChange,
  hijo,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  hijo: TreeRow | null
}) {
  const { loading, submit } = useSubmit(() => onOpenChange(false))
  if (!hijo?.bom_detalle_id) return null

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>¿Eliminar componente {hijo.codigo_variante}?</AlertDialogTitle>
          <AlertDialogDescription>
            Esta acción no se puede deshacer. El componente dejará de formar parte de la BOM.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancelar</AlertDialogCancel>
          <AlertDialogAction
            disabled={loading}
            onClick={() => submit(() => eliminarComponente(hijo.bom_detalle_id!))}
          >
            Eliminar
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}