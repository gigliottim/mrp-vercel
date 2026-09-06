'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { crearUsuario, actualizarUsuario } from './actions'

export type Usuario = { user_id: string; email: string; nombre: string; role_id: number; role_nombre: string }
export type Rol = { id: number; name: string }

export function UsuarioForm({
  initial,
  roles = [],
  onCancel,
}: {
  initial: Usuario | null
  roles?: Rol[]
  onSubmit?: never
  onCancel: () => void
}) {
  const [email, setEmail] = useState(initial?.email ?? '')
  const [nombre, setNombre] = useState(initial?.nombre ?? '')
  const [roleId, setRoleId] = useState(initial?.role_id ?? 0)
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')

  const handleSubmit = async () => {
    setError('')
    if (!email) return setError('Email requerido')
    if (!roleId) return setError('Seleccioná un rol')
    setBusy(true)
    const res = initial
      ? await actualizarUsuario(initial.user_id, { role_id: roleId, password: password || undefined })
      : await crearUsuario({ email, nombre, role_id: roleId, password: password || undefined })
    setBusy(false)
    if (!res.ok) setError(res.error ?? 'Error')
  }

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label>Email</Label>
          <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} disabled={!!initial} />
        </div>
        <div className="space-y-2">
          <Label>Nombre</Label>
          <Input value={nombre} onChange={(e) => setNombre(e.target.value)} disabled={!!initial} />
        </div>
        <div className="space-y-2">
          <Label>Rol</Label>
          <Select value={String(roleId)} onValueChange={(v) => setRoleId(Number(v))}>
            <SelectTrigger><SelectValue placeholder="Rol" /></SelectTrigger>
            <SelectContent>
              {roles.map((r) => <SelectItem key={r.id} value={String(r.id)}>{r.name}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label>{initial ? 'Nueva contraseña (opcional)' : 'Contraseña'}</Label>
          <Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder={initial ? 'Dejar vacío para no cambiar' : 'Mínimo 8 caracteres'} />
        </div>
      </div>
      {error ? <p className="text-sm text-destructive">{error}</p> : null}
      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={onCancel}>Cancelar</Button>
        <Button onClick={handleSubmit} disabled={busy}>{busy ? 'Guardando...' : 'Guardar'}</Button>
      </div>
    </div>
  )
}
