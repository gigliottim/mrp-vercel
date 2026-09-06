'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import { crearRol, eliminarRol } from './actions'

type Rol = { id: number; name: string; guard_name: string }

export function RolesClient({ roles, canAdmin }: { roles: Rol[]; canAdmin: boolean }) {
  const [name, setName] = useState('')
  const [list, setList] = useState(roles)
  const [busy, setBusy] = useState(false)

  const handleCrear = async () => {
    if (!name.trim()) return
    setBusy(true)
    const res = await crearRol({ name: name.trim() })
    setBusy(false)
    if (res.ok) {
      toast.success('Rol creado')
      setName('')
      window.location.reload()
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  const handleEliminar = async (r: Rol) => {
    if (!confirm(`¿Eliminar rol ${r.name}?`)) return
    const res = await eliminarRol(r.id)
    if (res.ok) {
      toast.success('Rol eliminado')
      setList((prev) => prev.filter((x) => x.id !== r.id))
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
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            />
          </div>
          <Button onClick={handleCrear} disabled={busy}>{busy ? 'Creando...' : 'Crear'}</Button>
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
                <td className="p-2">{r.name}</td>
                {canAdmin ? (
                  <td className="p-2">
                    <button onClick={() => handleEliminar(r)} className="text-sm text-destructive hover:underline">
                      Eliminar
                    </button>
                  </td>
                ) : null}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
