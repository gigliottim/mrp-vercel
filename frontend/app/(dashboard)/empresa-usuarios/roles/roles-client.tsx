'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { toast } from 'sonner'
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
import { crearRol, actualizarRol, eliminarRol } from './actions'

type Rol = { id: number; name: string; guard_name: string }

export function RolesClient({ roles, canAdmin }: { roles: Rol[]; canAdmin: boolean }) {
  const router = useRouter()
  const [name, setName] = useState('')
  const [list, setList] = useState(roles)
  const [busy, setBusy] = useState(false)
  const [editando, setEditando] = useState<Rol | null>(null)
  const [editName, setEditName] = useState('')
  const [porEliminar, setPorEliminar] = useState<Rol | null>(null)

  const handleCrear = async () => {
    if (!name.trim()) return
    setBusy(true)
    const res = await crearRol({ name: name.trim() })
    setBusy(false)
    if (res.ok) {
      toast.success('Rol creado')
      setName('')
      // router.refresh() conserva el estado client: actualizar la lista local
      setList((prev) => [...prev, { id: Date.now(), name: name.trim(), guard_name: 'web' }])
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  const handleActualizar = async () => {
    if (!editando || !editName.trim()) return
    setBusy(true)
    const res = await actualizarRol(editando.id, { name: editName.trim() })
    setBusy(false)
    if (res.ok) {
      toast.success('Rol actualizado')
      setList((prev) => prev.map((r) => (r.id === editando.id ? { ...r, name: editName.trim() } : r)))
      setEditando(null)
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  const handleEliminar = async () => {
    if (!porEliminar) return
    setBusy(true)
    const res = await eliminarRol(porEliminar.id)
    setBusy(false)
    if (res.ok) {
      toast.success('Rol eliminado')
      setList((prev) => prev.filter((x) => x.id !== porEliminar.id))
      setPorEliminar(null)
      router.refresh()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <div className="space-y-6">
      {canAdmin ? (
        <div className="flex max-w-md items-end gap-2">
          <div className="flex-1 space-y-2">
            <label className="text-sm font-medium">Nombre del rol</label>
            <Input value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <Button onClick={handleCrear} disabled={busy}>
            {busy ? 'Creando...' : 'Crear'}
          </Button>
        </div>
      ) : null}
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">ID</th>
              <th className="p-2 font-medium">Nombre</th>
              {canAdmin ? <th className="p-2 font-medium">Acciones</th> : null}
            </tr>
          </thead>
          <tbody>
            {list.map((r) => (
              <tr key={r.id} className="border-b">
                <td className="p-2">{r.id}</td>
                <td className="p-2">
                  {editando?.id === r.id ? (
                    <Input
                      value={editName}
                      onChange={(e) => setEditName(e.target.value)}
                      className="h-8 w-48"
                    />
                  ) : (
                    r.name
                  )}
                </td>
                {canAdmin ? (
                  <td className="p-2">
                    {editando?.id === r.id ? (
                      <span className="flex gap-2">
                        <Button size="sm" variant="outline" onClick={handleActualizar} disabled={busy}>
                          Guardar
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setEditando(null)}>
                          Cancelar
                        </Button>
                      </span>
                    ) : (
                      <span className="flex gap-2">
                        <button
                          className="text-sm hover:underline"
                          onClick={() => {
                            setEditando(r)
                            setEditName(r.name)
                          }}
                        >
                          Editar
                        </button>
                        <button
                          className="text-sm text-destructive hover:underline"
                          onClick={() => setPorEliminar(r)}
                        >
                          Eliminar
                        </button>
                      </span>
                    )}
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <AlertDialog open={porEliminar !== null} onOpenChange={(o) => (o ? null : setPorEliminar(null))}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>¿Eliminar rol {porEliminar?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              Esta acción no se puede deshacer.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={busy} onClick={handleEliminar}>
              Eliminar
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}