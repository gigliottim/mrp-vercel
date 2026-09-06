'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import { UsuarioForm, type Usuario, type Rol } from './usuarios-form'
import { eliminarUsuario } from './actions'

export function UsuariosClient({ usuarios, roles, canAdmin }: { usuarios: Usuario[]; roles: Rol[]; canAdmin: boolean }) {
  const [showForm, setShowForm] = useState(false)
  const [list, setList] = useState(usuarios)

  const handleEliminar = async (u: Usuario) => {
    if (!confirm(`¿Eliminar a ${u.email}?`)) return
    const res = await eliminarUsuario(u.user_id)
    if (res.ok) {
      toast.success('Usuario eliminado')
      setList((prev) => prev.filter((x) => x.user_id !== u.user_id))
    } else {
      toast.error(res.error ?? 'Error')
    }
  }

  return (
    <div className="space-y-6">
      {canAdmin ? (
        <>
          <Button onClick={() => setShowForm((v) => !v)}>
            {showForm ? 'Cerrar formulario' : 'Nuevo usuario'}
          </Button>
          {showForm ? (
            <UsuarioForm initial={null} roles={roles} onCancel={() => setShowForm(false)} />
          ) : null}
        </>
      ) : null}
      <div className="rounded-md border">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b bg-muted/50 text-left">
              <th className="p-2 font-medium">Email</th>
              <th className="p-2 font-medium">Nombre</th>
              <th className="p-2 font-medium">Rol</th>
              {canAdmin ? <th className="p-2 font-medium">Acciones</th> : null}
            </tr>
          </thead>
          <tbody>
            {list.map((u) => (
              <tr key={u.user_id} className="border-b">
                <td className="p-2">{u.email}</td>
                <td className="p-2">{u.nombre || '—'}</td>
                <td className="p-2">{u.role_nombre}</td>
                {canAdmin ? (
                  <td className="p-2">
                    <button onClick={() => handleEliminar(u)} className="text-sm text-destructive hover:underline">
                      Eliminar
                    </button>
                  </td>
                ) : null}
              </tr>
            ))}
            {list.length === 0 ? (
              <tr><td colSpan={4} className="h-16 text-center text-muted-foreground">Sin usuarios</td></tr>
            ) : null}
          </tbody>
        </table>
      </div>
    </div>
  )
}
