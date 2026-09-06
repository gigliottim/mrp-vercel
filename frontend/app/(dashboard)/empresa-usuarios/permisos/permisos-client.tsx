'use client'

import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { toast } from 'sonner'
import { guardarPermisos } from './actions'

type MenuItem = { id: number; label: string; section_key: string; section_label: string; parent_id: number | null; sort_order: number }
type AclRow = { id: number; menu_item_id: number; subject_type: string; subject_id: number; effect: string }
type Rol = { id: number; name: string }

export function PermisosClient({ items, acl, roles }: { items: MenuItem[]; acl: AclRow[]; roles: Rol[] }) {
  const [selectedRol, setSelectedRol] = useState(roles[0]?.id ?? 0)
  const [busy, setBusy] = useState(false)

  const aclFor = (menuItemId: number) =>
    acl.find((a) => a.menu_item_id === menuItemId && a.subject_type === 'role' && a.subject_id === selectedRol)

  const toggle = (menuItemId: number) => {
    const existing = aclFor(menuItemId)
    if (existing) {
      existing.effect = existing.effect === 'allow' ? 'deny' : 'allow'
    } else {
      acl.push({ id: 0, menu_item_id: menuItemId, subject_type: 'role', subject_id: selectedRol, effect: 'allow' })
    }
    // forzar re-render
    setSelectedRol((r) => r)
  }

  const handleGuardar = async () => {
    setBusy(true)
    const perms = acl
      .filter((a) => a.subject_type === 'role' && a.subject_id === selectedRol)
      .map((a) => ({ subject_type: 'role', subject_id: selectedRol, effect: a.effect }))
    const menuItemIds = [...new Set(acl.filter((a) => a.subject_type === 'role' && a.subject_id === selectedRol).map((a) => a.menu_item_id))]
    const res = await guardarPermisos({ menu_item_ids: menuItemIds, perms })
    setBusy(false)
    if (res.ok) toast.success('Permisos guardados')
    else toast.error(res.error ?? 'Error')
  }

  const secciones = [...new Set(items.map((i) => i.section_label))]

  return (
    <div className="space-y-4">
      <div className="flex items-end gap-4">
        <div className="w-64 space-y-2">
          <label className="text-sm font-medium">Rol</label>
          <select
            value={selectedRol}
            onChange={(e) => setSelectedRol(Number(e.target.value))}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
          >
            {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
        </div>
        <Button onClick={handleGuardar} disabled={busy}>{busy ? 'Guardando...' : 'Guardar permisos'}</Button>
      </div>

      {secciones.map((seccion) => (
        <div key={seccion} className="rounded-md border p-4">
          <h2 className="mb-2 font-semibold">{seccion}</h2>
          <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
            {items.filter((i) => i.section_label === seccion).map((item) => {
              const aclRow = aclFor(item.id)
              const allow = aclRow?.effect === 'allow'
              return (
                <label key={item.id} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={allow}
                    onChange={() => toggle(item.id)}
                    className="h-4 w-4"
                  />
                  {item.label}
                </label>
              )
            })}
          </div>
        </div>
      ))}
    </div>
  )
}
