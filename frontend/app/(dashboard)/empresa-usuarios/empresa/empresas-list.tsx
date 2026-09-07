'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
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
import { eliminarEmpresa } from './actions'

export type EmpresaItem = { company_id: number; companies: { id: number; name: string; slug: string } }

export function EmpresasList({ empresas, empresaActivaId }: { empresas: EmpresaItem[]; empresaActivaId: number }) {
  const router = useRouter()
  const [porEliminar, setPorEliminar] = useState<EmpresaItem | null>(null)
  const [busy, setBusy] = useState(false)

  if (empresas.length < 2) return null

  const handleEliminar = async () => {
    if (!porEliminar) return
    setBusy(true)
    const res = await eliminarEmpresa(porEliminar.companies.id)
    setBusy(false)
    if (res.ok) {
      toast.success('Empresa eliminada')
      setPorEliminar(null)
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <div className="rounded-md border">
      <h2 className="border-b p-4 font-semibold">Otras empresas ({empresas.length})</h2>
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b bg-muted/50 text-left">
            <th className="p-2 font-medium">Nombre</th>
            <th className="p-2 font-medium">Slug</th>
            <th className="p-2 font-medium">Estado</th>
            <th className="p-2 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          {empresas.map((e) => (
            <tr key={e.company_id} className="border-b">
              <td className="p-2">{e.companies.name}</td>
              <td className="p-2 text-muted-foreground">{e.companies.slug}</td>
              <td className="p-2">
                {e.company_id === empresaActivaId ? (
                  <span className="text-muted-foreground">Activa (no eliminable)</span>
                ) : (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={() => setPorEliminar(e)}
                  >
                    Eliminar
                  </Button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      <AlertDialog open={porEliminar !== null} onOpenChange={(o) => (o ? null : setPorEliminar(null))}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>¿Eliminar la empresa {porEliminar?.companies.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              Se eliminarán TODOS los datos de negocio de la empresa (partes, variantes, BOMs, órdenes,
              movimientos) y su base asociada. Esta acción no se puede deshacer.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={busy} onClick={handleEliminar}>
              {busy ? 'Eliminando...' : 'Eliminar definitivamente'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}